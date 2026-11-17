<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Actions\PaymentIntents\ConfirmPaymentIntent;
use App\Actions\PaymentIntents\CreatePaymentIntent;
use App\Data\PaymentIntentData;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\GatewayException;
use App\Models\PaymentIntent;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Support\GatewayAccount;
use Illuminate\Support\Facades\Cache;

/**
 * Charges the next period of a due subscription with its saved card, off-session.
 *
 * A cache lock per subscription keeps two workers from charging the same period; the unique
 * (subscription, period_start) index is the database-level backstop.
 */
final readonly class RenewSubscription
{
    public function __construct(
        private CreatePaymentIntent $createPaymentIntent,
        private ConfirmPaymentIntent $confirmPaymentIntent,
        private RecordInvoiceOutcome $recordInvoiceOutcome,
    ) {}

    public function handle(Subscription $subscription): void
    {
        Cache::lock('subscription-renewal:'.$subscription->id, 120)->get(function () use ($subscription) {
            $subscription->refresh()->load(['plan', 'merchant', 'paymentMethod']);

            if (! $subscription->status->isBillable() || $subscription->current_period_end->isFuture()) {
                return;
            }

            // A suspended merchant collects nothing; the invoice waits until the account is reactivated.
            if (! $subscription->merchant->canCollect($subscription->livemode)) {
                return;
            }

            if ($subscription->cancel_at_period_end) {
                $this->recordInvoiceOutcome->cancel($subscription, 'Cancelled at the end of the billing period.');

                return;
            }

            $invoice = $this->openInvoice($subscription);

            if ($invoice === null || ($invoice->next_payment_attempt !== null && $invoice->next_payment_attempt->isFuture())) {
                return;
            }

            $this->charge($subscription, $invoice);
        });
    }

    /**
     * True while the last renewal charge for this invoice still has no answer from the bank. Reconciliation
     * settles it; charging again now could take the money twice.
     */
    private function previousAttemptIsUnresolved(SubscriptionInvoice $invoice): bool
    {
        if ($invoice->payment_intent_id === null) {
            return false;
        }

        $intent = PaymentIntent::query()->find($invoice->payment_intent_id);

        if ($intent?->status === PaymentIntentStatus::Succeeded) {
            $this->recordInvoiceOutcome->paid($invoice);

            return true;
        }

        return $intent !== null && $intent->payments()->where('status', PaymentStatus::InProcess)->exists();
    }

    private function openInvoice(Subscription $subscription): ?SubscriptionInvoice
    {
        $invoice = SubscriptionInvoice::query()->firstOrCreate(
            ['subscription_id' => $subscription->id, 'period_start' => $subscription->current_period_end],
            [
                'merchant_id' => $subscription->merchant_id,
                'livemode' => $subscription->livemode,
                'amount_due' => $subscription->plan->amount,
                'currency' => $subscription->plan->currency,
                'status' => InvoiceStatus::Open,
                'period_end' => $subscription->plan->interval->advance($subscription->current_period_end, $subscription->plan->interval_count, $subscription->created_at?->day),
            ],
        );

        return $invoice->status === InvoiceStatus::Open ? $invoice : null;
    }

    private function charge(Subscription $subscription, SubscriptionInvoice $invoice): void
    {
        if ($this->previousAttemptIsUnresolved($invoice)) {
            return;
        }

        // Book the attempt and push the next one out before any money moves, so a crash mid-charge can
        // never let the scheduler charge the same invoice again within the hour.
        $invoice->update([
            'attempt_count' => $invoice->attempt_count + 1,
            'next_payment_attempt' => now()->addHour(),
        ]);

        if ($subscription->paymentMethod === null || $subscription->paymentMethod->isExpired()) {
            $this->recordInvoiceOutcome->failed($invoice, 'The saved card is missing or expired.');

            return;
        }

        try {
            $intent = $this->createPaymentIntent->handle(
                new GatewayAccount($subscription->merchant, $subscription->livemode),
                new PaymentIntentData(
                    amount: $invoice->amount_due,
                    clientReferenceId: $invoice->public_id,
                    returnUrl: route('subscriptions.authenticated'),
                    metadata: ['subscription' => $subscription->public_id, 'invoice' => $invoice->public_id],
                    paymentMethod: $subscription->paymentMethod,
                    expireInMinutes: 1440,
                ),
            );

            $invoice->update(['payment_intent_id' => $intent->id]);
            $intent = $this->confirmPaymentIntent->handle($intent);
        } catch (GatewayException $exception) {
            $this->recordInvoiceOutcome->failed($invoice->refresh(), $exception->getMessage());

            return;
        }

        $invoice->refresh();

        match ($intent->status) {
            PaymentIntentStatus::Succeeded => null,
            PaymentIntentStatus::RequiresAction => $this->recordInvoiceOutcome->failed($invoice, 'The bank asked the customer to authenticate this payment.'),
            default => $this->recordInvoiceOutcome->failed($invoice, 'The renewal payment was declined.'),
        };
    }
}
