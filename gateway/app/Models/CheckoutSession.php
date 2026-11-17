<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CheckoutPaymentStatus;
use App\Models\Concerns\BelongsToMerchant;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\CheckoutSessionFactory;
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
 * @property int|null $payment_link_id
 * @property int|null $plan_id
 * @property string $client_reference_id
 * @property string $mode
 * @property list<array{name: string, quantity: int, unit_amount: int}> $products
 * @property int $total_amount
 * @property string $currency
 * @property string $success_url
 * @property string $cancel_url
 * @property CheckoutPaymentStatus $payment_status
 * @property string $invoice
 * @property bool $save_card_on_success
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $expire_at
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $created_at
 * @property-read Merchant $merchant
 * @property-read Customer|null $customer
 * @property-read PaymentLink|null $paymentLink
 * @property-read Plan|null $plan
 */
#[Fillable([
    'merchant_id', 'livemode', 'customer_id', 'payment_link_id', 'plan_id', 'client_reference_id', 'mode', 'products',
    'total_amount', 'currency', 'success_url', 'cancel_url', 'payment_status', 'invoice',
    'save_card_on_success', 'metadata', 'expire_at', 'paid_at', 'cancelled_at',
])]
class CheckoutSession extends Model
{
    /** @use HasFactory<CheckoutSessionFactory> */
    use BelongsToMerchant, HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'livemode' => 'boolean',
            'products' => 'array',
            'total_amount' => 'integer',
            'payment_status' => CheckoutPaymentStatus::class,
            'save_card_on_success' => 'boolean',
            'metadata' => 'array',
            'expire_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'checkout_';
    }

    protected function publicIdLength(): int
    {
        return 32;
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<PaymentLink, $this>
     */
    public function paymentLink(): BelongsTo
    {
        return $this->belongsTo(PaymentLink::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isSubscription(): bool
    {
        return $this->mode === 'subscription';
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

    public function isPayable(): bool
    {
        return $this->payment_status === CheckoutPaymentStatus::Unpaid && ! $this->isExpired();
    }

    /**
     * Payable and the merchant may still collect in this mode: suspending a merchant stops their open
     * sessions immediately, not only their API keys.
     */
    public function canTakePayment(): bool
    {
        return $this->isPayable() && $this->merchant->canCollect($this->livemode);
    }
}
