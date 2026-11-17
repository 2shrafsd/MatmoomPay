<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToMerchant;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property bool $livemode
 * @property int $subscription_id
 * @property int|null $payment_intent_id
 * @property int $amount_due
 * @property int $amount_paid
 * @property string $currency
 * @property InvoiceStatus $status
 * @property int $attempt_count
 * @property CarbonImmutable|null $next_payment_attempt
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $created_at
 * @property-read Subscription $subscription
 * @property-read PaymentIntent|null $paymentIntent
 */
#[Fillable([
    'merchant_id', 'livemode', 'subscription_id', 'payment_intent_id', 'amount_due', 'amount_paid', 'currency',
    'status', 'attempt_count', 'next_payment_attempt', 'period_start', 'period_end', 'paid_at',
])]
class SubscriptionInvoice extends Model
{
    use BelongsToMerchant, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'livemode' => 'boolean',
            'amount_due' => 'integer',
            'amount_paid' => 'integer',
            'status' => InvoiceStatus::class,
            'attempt_count' => 'integer',
            'next_payment_attempt' => 'datetime',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'inv_';
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<PaymentIntent, $this>
     */
    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }
}
