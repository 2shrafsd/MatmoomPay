<?php

declare(strict_types=1);

namespace App\Actions\PaymentIntents;

use App\Data\PaymentIntentData;
use App\Enums\PaymentIntentStatus;
use App\Exceptions\GatewayException;
use App\Models\PaymentIntent;
use App\Support\GatewayAccount;
use App\Support\Money;

final class CreatePaymentIntent
{
    public function handle(GatewayAccount $account, PaymentIntentData $data): PaymentIntent
    {
        if ($data->amount < Money::minimumAmount()) {
            throw GatewayException::invalid('amount', Money::belowMinimumMessage());
        }

        if ($data->amount > (int) config('gateway.limits.max_total_amount')) {
            throw GatewayException::invalid('amount', 'The amount exceeds the maximum allowed for one payment.');
        }

        $intent = PaymentIntent::create([
            'merchant_id' => $account->merchant->id,
            'livemode' => $account->livemode,
            'customer_id' => $data->paymentMethod?->customer_id,
            'payment_method_id' => $data->paymentMethod?->id,
            'client_reference_id' => $data->clientReferenceId,
            'amount' => $data->amount,
            'currency' => (string) config('gateway.currency'),
            'return_url' => $data->returnUrl,
            'metadata' => $data->metadata,
            'status' => $data->paymentMethod ? PaymentIntentStatus::RequiresConfirmation : PaymentIntentStatus::RequiresPaymentMethod,
            'expire_at' => now()->addMinutes($data->expireInMinutes ?? (int) config('gateway.payment_intents.default_expiry_minutes')),
        ]);

        return $intent->setRelation('paymentMethod', $data->paymentMethod);
    }
}
