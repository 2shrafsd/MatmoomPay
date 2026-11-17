<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Checkout\ExpireCheckoutSession;
use App\Actions\PaymentIntents\CancelPaymentIntent;
use App\Actions\Payments\ApplyChargeResult;
use App\Actions\Payments\FailPayment;
use App\Actions\Refunds\CreateRefund;
use App\Enums\CheckoutPaymentStatus;
use App\Enums\PaymentIntentStatus;
use App\Enums\RefundStatus;
use App\Enums\WebhookDeliveryStatus;
use App\Exceptions\GatewayException;
use App\Jobs\DeliverWebhook;
use App\Models\CheckoutSession;
use App\Models\HostedPaymentAttempt;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\Refund;
use App\Models\WebhookDelivery;
use App\Services\Payments\Data\ChargeOutcome;
use App\Services\Payments\Exceptions\ProcessorUnavailable;
use App\Services\Payments\PaymentProcessorResolver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Settles everything whose outcome the gateway does not know yet by asking the processor: charges that
 * timed out, verifications the payer abandoned, refunds the bank did not confirm, and expired bank-page
 * attempts. Nothing is assumed; an unanswered question is simply asked again on the next run.
 */
#[Signature('gateway:reconcile')]
#[Description('Settle unknown payment outcomes and expire sessions and intents whose time ran out')]
class ReconcilePayments extends Command
{
    /**
     * Minutes a charge may stay unanswered before we ask the bank about it.
     */
    public const CHARGE_GRACE_MINUTES = 2;

    /**
     * Minutes a payer has to finish an OTP or 3-D Secure step.
     */
    public const CHALLENGE_WINDOW_MINUTES = 30;

    public const HEARTBEAT_KEY = 'gateway:scheduler-heartbeat';

    public function __construct(
        private readonly PaymentProcessorResolver $processors,
        private readonly ApplyChargeResult $applyChargeResult,
        private readonly FailPayment $failPayment,
        private readonly CreateRefund $createRefund,
        private readonly ExpireCheckoutSession $expireCheckoutSession,
        private readonly CancelPaymentIntent $cancelPaymentIntent,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $charges = $this->settleUnansweredCharges();
        $challenges = $this->closeAbandonedChallenges();
        $refunds = $this->resendPendingRefunds();
        $attempts = HostedPaymentAttempt::query()
            ->where('status', HostedPaymentAttempt::PENDING)
            ->where('expires_at', '<', now())
            ->update(['status' => HostedPaymentAttempt::EXPIRED]);

        $deliveries = $this->requeueStuckWebhooks();
        $sessions = $this->expireCheckoutSessions();
        $intents = $this->expirePaymentIntents();

        $this->info("Charges settled: {$charges}. Verifications closed: {$challenges}. Refunds settled: {$refunds}. Bank-page attempts expired: {$attempts}. Sessions expired: {$sessions}. Intents expired: {$intents}. Webhooks requeued: {$deliveries}.");

        // Production readiness and the health check use this to know the scheduler is really running.
        Cache::put(self::HEARTBEAT_KEY, now()->getTimestamp(), now()->addDay());

        return self::SUCCESS;
    }

    private function settleUnansweredCharges(): int
    {
        $settled = 0;

        Payment::query()
            ->beingCharged()
            ->where('created_at', '<=', now()->subMinutes(self::CHARGE_GRACE_MINUTES))
            ->chunkById(100, function (Collection $payments) use (&$settled): void {
                foreach ($payments as $payment) {
                    try {
                        $result = $this->processors->forPayment($payment)->retrieve($payment->public_id);
                    } catch (ProcessorUnavailable $exception) {
                        $this->report($payment, $exception);

                        continue;
                    }

                    if ($result === null) {
                        $this->failPayment->handle($payment, 'The bank has no record of this payment.');
                    } else {
                        $this->applyChargeResult->handle($payment, $result);
                    }

                    $settled++;
                }
            });

        return $settled;
    }

    private function closeAbandonedChallenges(): int
    {
        $closed = 0;

        Payment::query()
            ->awaitingChallenge()
            ->where('updated_at', '<=', now()->subMinutes(self::CHALLENGE_WINDOW_MINUTES))
            ->chunkById(100, function (Collection $payments) use (&$closed): void {
                foreach ($payments as $payment) {
                    try {
                        $result = $this->processors->forPayment($payment)->retrieve($payment->public_id);
                    } catch (ProcessorUnavailable $exception) {
                        $this->report($payment, $exception);

                        continue;
                    }

                    // The bank may have approved the challenge even though the payer never came back to us.
                    if ($result?->outcome === ChargeOutcome::Succeeded) {
                        $this->applyChargeResult->handle($payment, $result);
                    } else {
                        $this->failPayment->handle($payment, 'Verification was not completed in time.');
                    }

                    $closed++;
                }
            });

        return $closed;
    }

    private function resendPendingRefunds(): int
    {
        $settled = 0;

        Refund::query()
            ->with('payment')
            ->where('status', RefundStatus::Pending)
            ->where('created_at', '<=', now()->subMinutes(self::CHARGE_GRACE_MINUTES))
            ->chunkById(100, function (Collection $refunds) use (&$settled): void {
                foreach ($refunds as $refund) {
                    try {
                        $refund = $this->createRefund->send($refund);
                    } catch (ProcessorUnavailable $exception) {
                        $this->report($refund->payment, $exception);

                        continue;
                    }

                    if ($refund->status !== RefundStatus::Pending) {
                        $settled++;
                    }
                }
            });

        return $settled;
    }

    private function expireCheckoutSessions(): int
    {
        $expired = 0;

        CheckoutSession::query()
            ->where('payment_status', CheckoutPaymentStatus::Unpaid)
            ->where('expire_at', '<', now())
            ->chunkById(100, function (Collection $sessions) use (&$expired): void {
                foreach ($sessions as $session) {
                    $expired += (int) $this->expireCheckoutSession->handle($session);
                }
            });

        return $expired;
    }

    /**
     * An intent past its expiry can no longer be confirmed; cancelling it tells the merchant so.
     */
    private function expirePaymentIntents(): int
    {
        $expired = 0;

        PaymentIntent::query()
            ->whereNotIn('status', [PaymentIntentStatus::Succeeded, PaymentIntentStatus::Cancelled])
            ->where('expire_at', '<', now())
            ->chunkById(100, function (Collection $intents) use (&$expired): void {
                foreach ($intents as $intent) {
                    try {
                        $this->cancelPaymentIntent->handle($intent);
                        $expired++;
                    } catch (GatewayException) {
                        // A charge is still in flight; the next run looks again once it settles.
                    }
                }
            });

        return $expired;
    }

    /**
     * A delivery whose queued job was lost (worker crash, cleared queue) would wait forever; send it again.
     */
    private function requeueStuckWebhooks(): int
    {
        $requeued = 0;

        WebhookDelivery::query()
            ->where('status', WebhookDeliveryStatus::Pending)
            ->where('updated_at', '<', now()->subMinutes(15))
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<', now()->subMinutes(15)))
            ->chunkById(100, function (Collection $deliveries) use (&$requeued): void {
                foreach ($deliveries as $delivery) {
                    $delivery->touch();
                    DeliverWebhook::dispatch($delivery);
                    $requeued++;
                }
            });

        return $requeued;
    }

    private function report(Payment $payment, ProcessorUnavailable $exception): void
    {
        Log::warning('Reconciliation could not reach the processor.', ['payment' => $payment->public_id, 'error' => $exception->getMessage()]);
    }
}
