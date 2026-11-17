<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Exceptions\GatewayException;
use App\Models\CheckoutSession;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Services\Payments\Exceptions\ProcessorUnavailable;
use App\Services\Payments\PaymentProcessorResolver;
use Illuminate\Support\Facades\DB;

/**
 * Submits the payer's OTP / 3-D Secure code. The payment row (and what it pays for) stays locked for the whole
 * exchange, so parallel submissions cannot bypass the attempt limit or capture twice.
 */
final readonly class CompletePaymentChallenge
{
    public function __construct(
        private PaymentProcessorResolver $processors,
        private ApplyChargeResult $applyChargeResult,
    ) {}

    public function handle(Payment $payment, string $code): Payment
    {
        return DB::transaction(function () use ($payment, $code): Payment {
            // Lock what the payment is for before the payment itself: cancel and replace paths take the same order,
            // so a concurrent cancel waits here instead of deadlocking after the bank has answered.
            if ($payment->checkout_session_id !== null) {
                CheckoutSession::query()->whereKey($payment->checkout_session_id)->lockForUpdate()->first();
            }

            if ($payment->payment_intent_id !== null) {
                PaymentIntent::query()->whereKey($payment->payment_intent_id)->lockForUpdate()->first();
            }

            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! $payment->isAwaitingChallenge() || $payment->card_token === null || $payment->processor_reference === null) {
                throw GatewayException::cannotUpdate('This payment is not waiting for verification.');
            }

            try {
                $result = $this->processors->forPayment($payment)->completeChallenge(
                    $payment->card_token,
                    $payment->processor_reference,
                    $payment->challenge_type,
                    $code,
                );
            } catch (ProcessorUnavailable $exception) {
                throw $exception->configured ? GatewayException::processorTimeout() : GatewayException::processorUnavailable();
            }

            return $this->applyChargeResult->handle($payment, $result);
        });
    }
}
