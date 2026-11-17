<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Exceptions\GatewayException;
use App\Models\Payment;
use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Exceptions\ProcessorUnavailable;
use App\Services\Payments\PaymentProcessorResolver;

final readonly class ChargePayment
{
    public function __construct(
        private PaymentProcessorResolver $processors,
        private ApplyChargeResult $applyChargeResult,
        private FailPayment $failPayment,
    ) {}

    /**
     * Sends the charge outside any database lock. If the processor does not answer, the payment stays
     * in process: the bank may have captured it, so only reconciliation may decide its outcome.
     */
    public function handle(Payment $payment, bool $customerPresent): Payment
    {
        try {
            $result = $this->processors->forPayment($payment)->charge(new ChargeRequest(
                token: (string) $payment->card_token,
                amount: $payment->amount,
                currency: $payment->currency,
                reference: $payment->public_id,
                customerPresent: $customerPresent,
            ));
        } catch (ProcessorUnavailable $exception) {
            if ($exception->configured) {
                throw GatewayException::processorTimeout();
            }

            $this->failPayment->handle($payment, 'Card processing is not available for this payment.');

            throw GatewayException::processorUnavailable();
        }

        return $this->applyChargeResult->handle($payment, $result);
    }
}
