<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PaymentIntent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentIntent
 */
class PaymentIntentResource extends JsonResource
{
    /**
     * @var list<string>
     */
    public const RELATIONS = ['paymentMethod'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'client_reference_id' => $this->client_reference_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_method' => $this->paymentMethod?->public_id,
            'next_action' => $this->next_action,
            'status' => $this->status->value,
            'metadata' => (object) ($this->metadata ?? []),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'expire_at' => $this->expire_at->toIso8601ZuluString(),
        ];
    }
}
