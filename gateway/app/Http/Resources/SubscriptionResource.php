<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @var list<string>
     */
    public const RELATIONS = ['plan', 'customer', 'paymentMethod'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'status' => $this->status->value,
            'plan' => PlanResource::make($this->plan)->resolve($request),
            'customer_id' => $this->customer?->public_id,
            'payment_method' => $this->paymentMethod?->public_id,
            'current_period_start' => $this->current_period_start->toIso8601ZuluString(),
            'current_period_end' => $this->current_period_end->toIso8601ZuluString(),
            'cancel_at_period_end' => $this->cancel_at_period_end,
            'cancelled_at' => $this->cancelled_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
