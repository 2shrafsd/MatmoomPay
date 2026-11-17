<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CheckoutSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CheckoutSession
 */
class CheckoutSessionResource extends JsonResource
{
    /**
     * Relations that must be eager-loaded before rendering.
     *
     * @var list<string>
     */
    public const RELATIONS = ['customer', 'plan'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'session_id' => $this->public_id,
            'client_reference_id' => $this->client_reference_id,
            'customer_id' => $this->customer?->public_id,
            'products' => $this->products,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'success_url' => $this->success_url,
            'cancel_url' => $this->cancel_url,
            'payment_status' => $this->payment_status->value,
            'mode' => $this->mode,
            'plan_id' => $this->plan?->public_id,
            'invoice' => $this->invoice,
            'save_card_on_success' => $this->save_card_on_success,
            'metadata' => (object) ($this->metadata ?? []),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'expire_at' => $this->expire_at->toIso8601ZuluString(),
        ];
    }
}
