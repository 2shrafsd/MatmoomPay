<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SubscriptionInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionInvoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @var list<string>
     */
    public const RELATIONS = ['subscription', 'paymentIntent.paymentMethod'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'subscription_id' => $this->subscription->public_id,
            'status' => $this->status->value,
            'amount_due' => $this->amount_due,
            'amount_paid' => $this->amount_paid,
            'currency' => $this->currency,
            'attempt_count' => $this->attempt_count,
            'next_payment_attempt' => $this->next_payment_attempt?->toIso8601ZuluString(),
            'period_start' => $this->period_start->toIso8601ZuluString(),
            'period_end' => $this->period_end->toIso8601ZuluString(),
            'payment_intent' => $this->paymentIntent ? PaymentIntentResource::make($this->paymentIntent)->resolve($request) : null,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
