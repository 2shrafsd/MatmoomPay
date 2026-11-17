<?php

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Actions\Payments\ChargePayment;
use App\Exceptions\GatewayException;
use App\Models\CheckoutSession;
use App\Models\Payment;
use App\Models\PaymentMethod;

final readonly class PayCheckoutWithSavedCard
{
    public function __construct(
        private OpenCheckoutPayment $openCheckoutPayment,
        private ChargePayment $chargePayment,
    ) {}

    public function handle(CheckoutSession $session, PaymentMethod $card, ?string $ip): Payment
    {
        if ($session->customer_id === null || $card->customer_id !== $session->customer_id) {
            throw GatewayException::notFound();
        }

        if ($card->isExpired()) {
            throw GatewayException::paymentRejected('This card has expired.');
        }

        $payment = $this->openCheckoutPayment->handle($session, $card, $card->processor, false, $ip);

        return $this->chargePayment->handle($payment, customerPresent: true);
    }
}
