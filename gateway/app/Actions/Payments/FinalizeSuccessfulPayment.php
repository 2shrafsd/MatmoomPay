<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\Notifications\NotifyMerchantTeam;
use App\Actions\Subscriptions\RecordInvoiceOutcome;
use App\Actions\Subscriptions\StartSubscription;
use App\Actions\Webhooks\QueueWebhook;
use App\Enums\BalanceTransactionType;
use App\Enums\CheckoutPaymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentStatus;
use App\Enums\TeamNotification;
use App\Enums\WebhookEvent;
use App\Http\Resources\CheckoutSessionResource;
use App\Http\Resources\PaymentIntentResource;
use App\Http\Resources\PaymentResource;
use App\Jobs\RefundUnappliedPayment;
use App\Jobs\ReverseOrphanedCharge;
use App\Models\BalanceTransaction;
use App\Models\CheckoutSession;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\PaymentLink;
use App\Models\PaymentMethod;
use App\Models\SubscriptionInvoice;
use App\Notifications\PaymentReceipt;
use App\Notifications\PaymentReceived;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Marks a payment as captured and applies every consequence in one transaction: fee, ledger,
 * session or intent status, saved card and webhooks. Safe to call twice.
 */
final readonly class FinalizeSuccessfulPayment
{
    public function __construct(
        private SavePaymentMethod $savePaymentMethod,
        private QueueWebhook $queueWebhook,
        private StartSubscription $startSubscription,
        private RecordInvoiceOutcome $recordInvoiceOutcome,
        private NotifyMerchantTeam $notifyMerchantTeam,
    ) {}

    /**
     * Money the bank captured is always recorded. When it can no longer be applied (the session was already
     * paid or cancelled, the intent already succeeded, the payment link is used up) it is refunded
     * automatically instead of being silently kept or silently dropped.
     */
    public function handle(Payment $payment): Payment
    {
        $applied = true;

        $payment = DB::transaction(function () use ($payment, &$applied): Payment {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === PaymentStatus::Failed && $payment->processor_reference !== null) {
                Log::critical('The processor captured a payment that was already marked failed; reversing it.', ['payment' => $payment->public_id]);
                ReverseOrphanedCharge::dispatch($payment)->afterCommit();
            }

            if ($payment->status !== PaymentStatus::InProcess) {
                return $payment->load(PaymentResource::RELATIONS);
            }

            $merchant = $payment->merchant;
            $fee = min($payment->amount, Money::fee($payment->amount, $merchant->fee_rate_bps, $merchant->fee_fixed));
            $applied = $this->canApply($payment);

            $savedCard = $applied && $payment->save_card ? $this->savePaymentMethod->handle($payment) : null;

            $payment->fill([
                'status' => PaymentStatus::Successful,
                'fee' => $fee,
                'completed_at' => now(),
                'card_token' => null,
            ])->save();

            $this->recordLedger($payment);
            $session = $applied ? $this->completeCheckoutSession($payment, $savedCard) : null;

            if ($applied) {
                $this->completePaymentIntent($payment);
            }

            $payment->load(PaymentResource::RELATIONS);

            $this->queueWebhook->handle($merchant, $payment->livemode, WebhookEvent::PaymentSucceeded, PaymentResource::make($payment)->resolve());

            if ($payment->livemode) {
                $this->notifyMerchantTeam->handle($merchant, TeamNotification::PaymentReceived, new PaymentReceived($payment));
            }

            if ($session !== null) {
                $this->queueWebhook->handle($merchant, $payment->livemode, WebhookEvent::CheckoutCompleted, CheckoutSessionResource::make($session)->resolve());
                $this->sendReceipt($payment, $session);
            }

            if (! $applied) {
                RefundUnappliedPayment::dispatch($payment)->afterCommit();
            }

            return $payment;
        });

        return $payment;
    }

    /**
     * Locks what the payment is for and checks it can still take it.
     */
    private function canApply(Payment $payment): bool
    {
        if ($payment->checkout_session_id !== null) {
            $session = CheckoutSession::query()->whereKey($payment->checkout_session_id)->lockForUpdate()->firstOrFail();

            if ($session->payment_status !== CheckoutPaymentStatus::Unpaid) {
                return false;
            }

            if ($session->payment_link_id !== null) {
                $link = PaymentLink::query()->whereKey($session->payment_link_id)->lockForUpdate()->first();

                if ($link !== null && $link->max_uses !== null && $link->uses_count >= $link->max_uses) {
                    return false;
                }
            }
        }

        if ($payment->payment_intent_id !== null) {
            $intent = PaymentIntent::query()->whereKey($payment->payment_intent_id)->lockForUpdate()->first();

            if ($intent !== null && in_array($intent->status, [PaymentIntentStatus::Succeeded, PaymentIntentStatus::Cancelled], true)) {
                return false;
            }

            // A renewal whose invoice was voided (subscription cancelled) cannot be applied; it is refunded instead.
            $invoice = SubscriptionInvoice::query()->where('payment_intent_id', $payment->payment_intent_id)->lockForUpdate()->first();

            if ($invoice !== null && $invoice->status !== InvoiceStatus::Open) {
                return false;
            }
        }

        return true;
    }

    /**
     * Payment-link payers who gave an email address get a receipt once the transaction commits.
     */
    private function sendReceipt(Payment $payment, CheckoutSession $session): void
    {
        $email = $session->metadata['Customer email'] ?? null;

        if ($session->payment_link_id === null || ! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        if (! $payment->livemode && ! $payment->merchant->isTeamEmail($email)) {
            return;
        }

        // The payer has no account, so the receipt uses the language they paid in.
        Notification::route('mail', $email)->notify((new PaymentReceipt($payment, $session->load('merchant')))->locale(app()->getLocale())->afterCommit());
    }

    private function recordLedger(Payment $payment): void
    {
        $base = [
            'merchant_id' => $payment->merchant_id,
            'livemode' => $payment->livemode,
            'currency' => $payment->currency,
            'source_type' => $payment->getMorphClass(),
            'source_id' => $payment->id,
        ];

        BalanceTransaction::create([...$base, 'type' => BalanceTransactionType::Payment, 'amount' => $payment->amount, 'description' => 'Payment '.$payment->public_id]);

        if ($payment->fee > 0) {
            BalanceTransaction::create([...$base, 'type' => BalanceTransactionType::Fee, 'amount' => -$payment->fee, 'description' => 'Fee for payment '.$payment->public_id]);
        }
    }

    private function completeCheckoutSession(Payment $payment, ?PaymentMethod $savedCard): ?CheckoutSession
    {
        if ($payment->checkout_session_id === null) {
            return null;
        }

        $session = CheckoutSession::query()->whereKey($payment->checkout_session_id)->lockForUpdate()->firstOrFail();

        $session->update([
            'payment_status' => CheckoutPaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        if ($session->payment_link_id !== null) {
            PaymentLink::query()->whereKey($session->payment_link_id)->increment('uses_count');
        }

        $this->startSubscription->handle($session, $payment, $savedCard);

        return $session->load(CheckoutSessionResource::RELATIONS);
    }

    private function completePaymentIntent(Payment $payment): void
    {
        if ($payment->payment_intent_id === null) {
            return;
        }

        $intent = PaymentIntent::query()->whereKey($payment->payment_intent_id)->firstOrFail();
        $intent->update([
            'status' => PaymentIntentStatus::Succeeded,
            'next_action' => null,
            'succeeded_at' => now(),
        ]);

        $this->queueWebhook->handle($payment->merchant, $payment->livemode, WebhookEvent::PaymentIntentSucceeded, PaymentIntentResource::make($intent->load(PaymentIntentResource::RELATIONS))->resolve());

        $invoice = SubscriptionInvoice::query()
            ->where('payment_intent_id', $payment->payment_intent_id)
            ->where('status', InvoiceStatus::Open)
            ->first();

        if ($invoice !== null) {
            $this->recordInvoiceOutcome->paid($invoice);
        }
    }
}
