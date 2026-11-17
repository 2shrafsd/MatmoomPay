<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Data\CheckoutSessionData;
use App\Exceptions\GatewayException;
use App\Models\Customer;
use App\Models\Plan;
use App\Rules\Metadata;

class StoreCheckoutSessionRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $limits = config('gateway.limits');
        $checkout = config('gateway.checkout');

        return [
            'client_reference_id' => ['required', 'string', 'max:191'],
            'mode' => ['required', 'string', 'in:payment,subscription'],
            'plan_id' => ['required_if:mode,subscription', 'prohibited_unless:mode,subscription', 'nullable', 'string', 'max:32'],
            'products' => ['exclude_if:mode,subscription', 'required', 'list', 'min:1', 'max:'.$limits['max_products']],
            'products.*' => ['array:name,quantity,unit_amount'],
            'products.*.name' => ['required', 'string', 'max:40'],
            'products.*.quantity' => ['required', 'integer', 'min:1', 'max:'.$limits['max_product_quantity']],
            'products.*.unit_amount' => ['required', 'integer', 'min:1', 'max:'.$limits['max_unit_amount']],
            'success_url' => $this->urlRules(),
            'cancel_url' => $this->urlRules(),
            'customer_id' => ['required_if:mode,subscription', 'nullable', 'string', 'max:40'],
            'save_card_on_success' => ['sometimes', 'boolean'],
            'expire_in_minutes' => ['nullable', 'integer', 'min:'.$checkout['min_expiry_minutes'], 'max:'.$checkout['max_expiry_minutes']],
            'metadata' => ['present', new Metadata],
        ];
    }

    public function toData(): CheckoutSessionData
    {
        $validated = $this->validated();
        $customer = null;

        if (filled($validated['customer_id'] ?? null)) {
            $customer = Customer::query()
                ->forAccount($this->account()->merchant, $this->account()->livemode)
                ->where('public_id', $validated['customer_id'])
                ->first() ?? throw GatewayException::invalid('customer_id', 'No customer found with this id.');
        }

        $plan = null;

        if (filled($validated['plan_id'] ?? null)) {
            $plan = Plan::query()
                ->forAccount($this->account()->merchant, $this->account()->livemode)
                ->where('public_id', $validated['plan_id'])
                ->where('is_active', true)
                ->first() ?? throw GatewayException::invalid('plan_id', 'No active plan found with this id.');
        }

        return new CheckoutSessionData(
            clientReferenceId: $validated['client_reference_id'],
            products: array_values(array_map(fn (array $product): array => [
                'name' => (string) $product['name'],
                'quantity' => (int) $product['quantity'],
                'unit_amount' => (int) $product['unit_amount'],
            ], $validated['products'] ?? [])),
            successUrl: $validated['success_url'],
            cancelUrl: $validated['cancel_url'],
            metadata: $validated['metadata'] ?? [],
            customer: $customer,
            saveCardOnSuccess: (bool) ($validated['save_card_on_success'] ?? false),
            expireInMinutes: isset($validated['expire_in_minutes']) ? (int) $validated['expire_in_minutes'] : null,
            plan: $plan,
        );
    }
}
