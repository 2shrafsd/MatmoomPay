<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Data\PaymentIntentData;
use App\Exceptions\GatewayException;
use App\Models\PaymentMethod;
use App\Rules\Metadata;

class StorePaymentIntentRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $intents = config('gateway.payment_intents');

        return [
            'payment_method_id' => ['nullable', 'string', 'max:40'],
            'amount' => ['required', 'integer', 'min:1', 'max:'.config('gateway.limits.max_total_amount')],
            'client_reference_id' => ['required', 'string', 'max:191'],
            'return_url' => $this->urlRules(),
            'metadata' => ['present', new Metadata],
            'expire_in_minutes' => ['nullable', 'integer', 'min:'.$intents['min_expiry_minutes'], 'max:'.$intents['max_expiry_minutes']],
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

    public function toData(): PaymentIntentData
    {
        $validated = $this->validated();

        return new PaymentIntentData(
            amount: (int) $validated['amount'],
            clientReferenceId: $validated['client_reference_id'],
            returnUrl: $validated['return_url'],
            metadata: $validated['metadata'] ?? [],
            paymentMethod: $this->paymentMethod(),
            expireInMinutes: isset($validated['expire_in_minutes']) ? (int) $validated['expire_in_minutes'] : null,
        );
    }
}
