<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @var list<string>
     */
    public const RELATIONS = ['merchant', 'checkoutSession', 'paymentIntent', 'refunds.payment'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'activity' => $this->merchant->activity,
            'payment_id' => $this->public_id,
            'masked_card' => $this->masked_card,
            'card_type' => $this->card_type->value,
            'brand' => $this->card_brand->value,
            'status' => $this->status->value,
            'reason' => $this->failure_reason,
            'amount' => $this->amount,
            'fee' => $this->fee,
            'currency' => $this->currency,
            'refunded' => $this->isRefunded(),
            'refunded_amount' => $this->refunded_amount,
            'refunds' => $this->refunds->isEmpty() ? null : RefundResource::collection($this->refunds)->resolve($request),
            'payment_intent' => $this->paymentIntent?->public_id,
            'checkout_invoice' => $this->checkoutSession?->invoice,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
