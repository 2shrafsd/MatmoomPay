<?php

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Actions\Payments\ChargePayment;
use App\Exceptions\GatewayException;
use App\Models\CheckoutSession;
use App\Models\Payment;
use App\Services\Payments\Data\CardDetails;
use App\Services\Payments\Exceptions\CardRejected;
use App\Services\Payments\Exceptions\ProcessorUnavailable;
use App\Services\Payments\PaymentProcessorResolver;
use SensitiveParameter;

final readonly class PayCheckoutWithCard
{
    public function __construct(
        private PaymentProcessorResolver $processors,
        private OpenCheckoutPayment $openCheckoutPayment,
        private ChargePayment $chargePayment,
    ) {}

    public function handle(CheckoutSession $session, #[SensitiveParameter] CardDetails $card, bool $saveCard, ?string $ip): Payment
    {
        if (! $session->canTakePayment()) {
            throw GatewayException::cannotUpdate('This checkout session can no longer be paid.');
        }

        $processor = $this->processors->for($session->livemode);

        try {
            $token = $processor->tokenize($card);
        } catch (ProcessorUnavailable) {
            throw GatewayException::processorUnavailable();
        } catch (CardRejected $exception) {
            throw GatewayException::paymentRejected($exception->getMessage());
        }

        $payment = $this->openCheckoutPayment->handle($session, $token, $processor->name(), $saveCard, $ip);

        return $this->chargePayment->handle($payment, customerPresent: true);
    }
}
