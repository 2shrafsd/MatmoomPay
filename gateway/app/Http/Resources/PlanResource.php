<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'interval' => $this->interval->value,
            'interval_count' => $this->interval_count,
            'active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
