<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Exceptions\GatewayException;
use App\Models\PaymentMethod;

class ConfirmPaymentIntentRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_method_id' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function paymentMethod(): ?PaymentMethod
    {
        $id = $this->validated('payment_method_id');

        if (blank($id)) {
            return null;
        }

        return PaymentMethod::query()
            ->forAccount($this->account()->merchant, $this->account()->livemode)
            ->where('public_id', $id)
            ->first() ?? throw GatewayException::invalid('payment_method_id', 'No payment method found with this id.');
    }
}
