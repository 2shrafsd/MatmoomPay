<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\PaymentMethod;

/**
 * Stores the card used in a successful payment against the session's customer. A card the customer
 * already saved is refreshed instead of duplicated.
 */
final class SavePaymentMethod
{
    public function handle(Payment $payment): ?PaymentMethod
    {
        if ($payment->customer_id === null || $payment->card_token === null || $payment->card_fingerprint === null) {
            return null;
        }

        return PaymentMethod::updateOrCreate(
            ['customer_id' => $payment->customer_id, 'fingerprint' => $payment->card_fingerprint],
            [
                'merchant_id' => $payment->merchant_id,
                'livemode' => $payment->livemode,
                'processor' => $payment->processor,
                'processor_token' => $payment->card_token,
                'bin' => $payment->card_bin,
                'last_four' => $payment->card_last_four,
                'brand' => $payment->card_brand,
                'card_type' => $payment->card_type,
                'expiry_month' => (int) $payment->card_expiry_month,
                'expiry_year' => (int) $payment->card_expiry_year,
            ],
        );
    }
}
