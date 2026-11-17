<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Actions\Notifications\NotifyMerchantTeam;
use App\Actions\PaymentIntents\CancelPaymentIntent;
use App\Actions\Webhooks\QueueWebhook;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TeamNotification;
use App\Enums\WebhookEvent;
use App\Exceptions\GatewayException;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\PaymentIntent;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Notifications\RenewalFailed;

/**
 * Applies the result of a renewal charge to the invoice and its subscription.
 *
 * Failed renewals are retried 1, 3 and 5 days after the first attempt; after the fourth failure the
 * invoice is voided and the subscription cancelled.
 */
final readonly class RecordInvoiceOutcome
{
    /**
     * Days to wait after each failed attempt (indexed by attempt number - 1).
     *
     * @var list<int>
     */
    public const RETRY_AFTER_DAYS = [1, 2, 2];

    public const MAX_ATTEMPTS = 4;

    public function __construct(
        private QueueWebhook $queueWebhook,
        private CancelPaymentIntent $cancelPaymentIntent,
        private NotifyMerchantTeam $notifyMerchantTeam,
    ) {}

    public function paid(SubscriptionInvoice $invoice): void
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            return;
        }

        $invoice->update([
            'status' => InvoiceStatus::Paid,
            'amount_paid' => $invoice->amount_due,
            'next_payment_attempt' => null,
            'paid_at' => now(),
        ]);

        $subscription = $invoice->subscription;

        if ($subscription->status !== SubscriptionStatus::Cancelled) {
            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'current_period_start' => $invoice->period_start,
                'current_period_end' => $invoice->period_end,
            ]);
        }

        $invoice->load(InvoiceResource::RELATIONS);
        $this->queueWebhook->handle($invoice->subscription->merchant, $invoice->livemode, WebhookEvent::InvoicePaid, InvoiceResource::make($invoice)->resolve());
    }

    public function failed(SubscriptionInvoice $invoice, string $reason): void
    {
        $attempts = $invoice->attempt_count;
        $subscription = $invoice->subscription;

        if ($invoice->livemode) {
            $this->notifyMerchantTeam->handle($subscription->merchant, TeamNotification::RenewalFailed, new RenewalFailed($invoice, $reason));
        }

        if ($attempts >= self::MAX_ATTEMPTS) {
            $invoice->update(['status' => InvoiceStatus::Void, 'next_payment_attempt' => null]);
            $this->cancel($subscription, 'Renewal payment failed '.self::MAX_ATTEMPTS.' times.');

            return;
        }

        $invoice->update(['next_payment_attempt' => now()->addDays(self::RETRY_AFTER_DAYS[$attempts - 1] ?? 2)]);
        $subscription->update(['status' => SubscriptionStatus::PastDue]);

        $invoice->load(InvoiceResource::RELATIONS);
        $data = [...InvoiceResource::make($invoice)->resolve(), 'failure_reason' => $reason];

        $this->queueWebhook->handle($subscription->merchant, $subscription->livemode, WebhookEvent::InvoicePaymentFailed, $data);
        $this->queueWebhook->handle($subscription->merchant, $subscription->livemode, WebhookEvent::SubscriptionPastDue, SubscriptionResource::make($subscription->load(SubscriptionResource::RELATIONS))->resolve());
    }

    public function cancel(Subscription $subscription, string $reason): void
    {
        if ($subscription->status === SubscriptionStatus::Cancelled) {
            return;
        }

        $subscription->update([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        $this->cancelOpenRenewals($subscription);

        $subscription->invoices()->where('status', InvoiceStatus::Open)->update(['status' => InvoiceStatus::Void, 'next_payment_attempt' => null]);

        $subscription->load(SubscriptionResource::RELATIONS);
        $this->queueWebhook->handle($subscription->merchant, $subscription->livemode, WebhookEvent::SubscriptionCancelled, SubscriptionResource::make($subscription)->resolve());
    }

    /**
     * A renewal waiting for 3-D Secure must not be completable after the subscription ends.
     */
    private function cancelOpenRenewals(Subscription $subscription): void
    {
        $intents = PaymentIntent::query()
            ->whereIn('id', $subscription->invoices()->where('status', InvoiceStatus::Open)->whereNotNull('payment_intent_id')->select('payment_intent_id'))
            ->get();

        foreach ($intents as $intent) {
            if ($intent->status->isFinal()) {
                continue;
            }

            try {
                $this->cancelPaymentIntent->handle($intent);
            } catch (GatewayException) {
                // A charge is in flight; reconciliation settles it and a late success is refunded automatically.
            }
        }
    }
}
