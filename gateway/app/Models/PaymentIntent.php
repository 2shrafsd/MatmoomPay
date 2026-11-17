<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentIntentStatus;
use App\Models\Concerns\BelongsToMerchant;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentIntentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property bool $livemode
 * @property int|null $customer_id
 * @property int|null $payment_method_id
 * @property string $client_reference_id
 * @property int $amount
 * @property string $currency
 * @property string $return_url
 * @property array<string, mixed>|null $metadata
 * @property PaymentIntentStatus $status
 * @property array{url: string, return_url: string}|null $next_action
 * @property CarbonImmutable $expire_at
 * @property CarbonImmutable|null $succeeded_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $created_at
 * @property-read Merchant $merchant
 * @property-read PaymentMethod|null $paymentMethod
 */
#[Fillable([
    'merchant_id', 'livemode', 'customer_id', 'payment_method_id', 'client_reference_id', 'amount', 'currency',
    'return_url', 'metadata', 'status', 'next_action', 'expire_at', 'succeeded_at', 'cancelled_at',
])]
class PaymentIntent extends Model
{
    /** @use HasFactory<PaymentIntentFactory> */
    use BelongsToMerchant, HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'livemode' => 'boolean',
            'amount' => 'integer',
            'metadata' => 'array',
            'status' => PaymentIntentStatus::class,
            'next_action' => 'array',
            'expire_at' => 'datetime',
            'succeeded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'pi_';
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isExpired(): bool
    {
        return $this->expire_at->isPast();
    }
}
