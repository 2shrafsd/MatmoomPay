<?php

declare(strict_types=1);

namespace App\Actions\Refunds;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Notifications\NotifyMerchantTeam;
use App\Actions\Webhooks\QueueWebhook;
use App\Enums\BalanceTransactionType;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\TeamNotification;
use App\Enums\WebhookEvent;
use App\Exceptions\GatewayException;
use App\Http\Resources\RefundResource;
use App\Models\BalanceTransaction;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\RefundProcessed;
use App\Notifications\RefundReceipt;
use App\Services\Payments\Data\RefundResult;
use App\Services\Payments\Exceptions\ProcessorUnavailable;
use App\Services\Payments\PaymentProcessorResolver;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Refunds all or part of a successful payment. The payment row is locked so concurrent requests
 * can never refund more than was captured.
 */
/**
 * Refunds are recorded as pending before the bank is called, and the refund ID goes to the bank as its
 * idempotency key. A timeout therefore leaves a pending refund that reconciliation resends safely,
 * instead of an unknown outcome that could be refunded twice.
 */
final readonly class CreateRefund
{
    public function __construct(
        private PaymentProcessorResolver $processors,
        private QueueWebhook $queueWebhook,
        private RecordAuditLog $recordAuditLog,
        private NotifyMerchantTeam $notifyMerchantTeam,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(Payment $payment, ?int $amount, string $reason, array $metadata = [], ?User $actor = null): Refund
    {
        $refund = DB::transaction(function () use ($payment, $amount, $reason, $metadata, $actor): Refund {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== PaymentStatus::Successful) {
                throw GatewayException::refundNotAllowed('Only successful payments can be refunded.');
            }

            $refundable = $payment->refundableAmount();

            if ($refundable === 0) {
                throw GatewayException::alreadyRefunded();
            }

            $amount ??= $refundable;

            if ($amount > $refundable) {
                throw GatewayException::invalid('amount', 'The amount is larger than the refundable balance of '.Money::format($refundable, $payment->currency).'.');
            }

            return Refund::create([
                'merchant_id' => $payment->merchant_id,
                'livemode' => $payment->livemode,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'reason' => $reason,
                'metadata' => $metadata,
                'status' => RefundStatus::Pending,
                'created_by' => $actor?->id,
            ]);
        });

        try {
            $refund = $this->send($refund);
        } catch (ProcessorUnavailable) {
            $refund->delete();

            throw GatewayException::processorUnavailable();
        }

        if ($actor !== null) {
            $this->recordAuditLog->handle($actor, 'refund.created', $refund->payment->merchant, $refund, ['amount' => $refund->amount, 'payment' => $refund->payment->public_id]);
        }

        return $refund;
    }

    /**
     * Sends a pending refund to the bank (again). Safe to repeat: the bank deduplicates on the refund ID.
     *
     * @throws ProcessorUnavailable when no processor is configured for this payment
     */
    public function send(Refund $refund): Refund
    {
        $payment = $refund->payment;

        try {
            $result = $this->processors->forPayment($payment)->refund((string) $payment->processor_reference, $refund->amount, $payment->currency, $refund->public_id);
        } catch (ProcessorUnavailable $exception) {
            if (! $exception->configured) {
                throw $exception;
            }

            return $refund;
        }

        return $this->settle($refund, $result);
    }

    private function settle(Refund $refund, RefundResult $result): Refund
    {
        return DB::transaction(function () use ($refund, $result): Refund {
            $refund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($refund->payment_id)->lockForUpdate()->firstOrFail();

            if ($refund->status !== RefundStatus::Pending) {
                return $refund->setRelation('payment', $payment);
            }

            $refund->update([
                'status' => $result->successful ? RefundStatus::Successful : RefundStatus::Failed,
                'processor_reference' => $result->processorReference,
                'failure_reason' => $result->failureReason,
            ]);

            if ($result->successful) {
                $payment->increment('refunded_amount', $refund->amount);

                BalanceTransaction::create([
                    'merchant_id' => $payment->merchant_id,
                    'livemode' => $payment->livemode,
                    'type' => BalanceTransactionType::Refund,
                    'amount' => -$refund->amount,
                    'currency' => $payment->currency,
                    'source_type' => $refund->getMorphClass(),
                    'source_id' => $refund->id,
                    'description' => 'Refund for payment '.$payment->public_id,
                ]);
            }

            $refund->setRelation('payment', $payment);

            if ($result->successful) {
                $this->announce($refund);
            }

            $this->queueWebhook->handle(
                $payment->merchant,
                $payment->livemode,
                $result->successful ? WebhookEvent::RefundSucceeded : WebhookEvent::RefundFailed,
                RefundResource::make($refund)->resolve(),
            );

            return $refund;
        });
    }

    /**
     * The team hears about live refunds; a payer who left an email on a payment link gets a refund receipt.
     */
    private function announce(Refund $refund): void
    {
        $payment = $refund->payment;

        if ($payment->livemode) {
            $this->notifyMerchantTeam->handle($payment->merchant, TeamNotification::RefundProcessed, new RefundProcessed($refund));
        }

        $session = $payment->checkoutSession;
        $email = $session?->metadata['Customer email'] ?? null;

        if ($session === null || $session->payment_link_id === null || ! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        // Sandbox refunds are free and unlimited, so their receipts only go to the merchant's own team.
        if (! $payment->livemode && ! $payment->merchant->isTeamEmail($email)) {
            return;
        }

        Notification::route('mail', $email)->notify((new RefundReceipt($refund, $session->load('merchant')))->locale(app()->getLocale())->afterCommit());
    }
}
