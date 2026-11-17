<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Exceptions\GatewayException;
use App\Models\Payment;
use App\Services\Payments\Exceptions\ProcessorUnavailable;
use App\Services\Payments\PaymentProcessorResolver;
use Illuminate\Support\Facades\DB;

/**
 * Submits the payer's OTP / 3-D Secure code. The payment row stays locked for the whole exchange,
 * so parallel submissions cannot bypass the attempt limit or capture twice.
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
