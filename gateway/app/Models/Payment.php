<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CardBrand;
use App\Enums\CardType;
use App\Enums\ChallengeType;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Concerns\BelongsToMerchant;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property bool $livemode
 * @property int|null $checkout_session_id
 * @property int|null $payment_intent_id
 * @property int|null $payment_method_id
 * @property int|null $customer_id
 * @property int $amount
 * @property int $fee
 * @property int $refunded_amount
 * @property string $currency
 * @property PaymentStatus $status
 * @property string|null $failure_reason
 * @property string $masked_card
 * @property string $card_bin
 * @property string $card_last_four
 * @property CardBrand $card_brand
 * @property CardType $card_type
 * @property string|null $card_fingerprint
 * @property int|null $card_expiry_month
 * @property int|null $card_expiry_year
 * @property string $processor
 * @property string|null $processor_reference
 * @property string|null $card_token
 * @property ChallengeType|null $challenge_type
 * @property int $challenge_attempts
 * @property bool $save_card
 * @property string|null $ip_address
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property-read Merchant $merchant
 * @property-read CheckoutSession|null $checkoutSession
 * @property-read PaymentIntent|null $paymentIntent
 * @property-read Customer|null $customer
 */
#[Fillable([
    'public_id', 'merchant_id', 'livemode', 'checkout_session_id', 'payment_intent_id', 'payment_method_id',
    'customer_id', 'amount', 'currency', 'status', 'masked_card', 'card_bin', 'card_last_four', 'card_brand',
    'card_type', 'card_fingerprint', 'card_expiry_month', 'card_expiry_year', 'processor', 'card_token',
    'save_card', 'ip_address', 'fee', 'refunded_amount', 'failure_reason', 'processor_reference', 'challenge_type',
    'challenge_attempts', 'completed_at',
])]
#[Hidden(['card_token', 'processor_reference', 'card_fingerprint'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToMerchant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'livemode' => 'boolean',
            'amount' => 'integer',
            'fee' => 'integer',
            'refunded_amount' => 'integer',
            'status' => PaymentStatus::class,
            'card_brand' => CardBrand::class,
            'card_type' => CardType::class,
            'card_token' => 'encrypted',
            'challenge_type' => ChallengeType::class,
            'challenge_attempts' => 'integer',
            'save_card' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsTo<CheckoutSession, $this>
     */
    public function checkoutSession(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class);
    }

    /**
     * @return BelongsTo<PaymentIntent, $this>
     */
    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function refundableAmount(): int
    {
        if ($this->status !== PaymentStatus::Successful) {
            return 0;
        }

        $reserved = (int) $this->refunds()->where('status', RefundStatus::Pending)->sum('amount');

        return max(0, $this->amount - $this->refunded_amount - $reserved);
    }

    public function isRefunded(): bool
    {
        return $this->refunded_amount > 0;
    }

    /**
     * A charge that was sent to the processor and has no answer yet. It must never be replaced or failed
     * locally, because the bank may still capture it; only the processor's answer or reconciliation settles it.
     */
    public function isBeingCharged(): bool
    {
        return $this->status === PaymentStatus::InProcess && $this->challenge_type === null;
    }

    /**
     * @param  Builder<Payment>  $query
     */
    #[Scope]
    protected function beingCharged(Builder $query): void
    {
        $query->where('status', PaymentStatus::InProcess)->whereNull('challenge_type');
    }

    /**
     * @param  Builder<Payment>  $query
     */
    #[Scope]
    protected function awaitingChallenge(Builder $query): void
    {
        $query->where('status', PaymentStatus::InProcess)->whereNotNull('challenge_type');
    }

    public function isAwaitingChallenge(): bool
    {
        return $this->status === PaymentStatus::InProcess && $this->challenge_type !== null;
    }
}
