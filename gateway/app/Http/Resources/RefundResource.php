<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Refund
 */
class RefundResource extends JsonResource
{
    /**
     * @var list<string>
     */
    public const RELATIONS = ['payment'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'refund_id' => $this->public_id,
            'payment_id' => $this->payment->public_id,
            'amount' => $this->amount,
            'status' => $this->status->value,
            'reason' => $this->reason,
            'metadata' => (object) ($this->metadata ?? []),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
