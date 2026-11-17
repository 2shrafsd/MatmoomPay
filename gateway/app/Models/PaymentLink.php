<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToMerchant;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property bool $livemode
 * @property string $title
 * @property string|null $description
 * @property int|null $amount
 * @property int|null $min_amount
 * @property int|null $max_amount
 * @property string $currency
 * @property bool $is_active
 * @property int|null $max_uses
 * @property int $uses_count
 * @property bool $collect_phone
 * @property bool $collect_email
 * @property CarbonImmutable|null $expires_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property-read Merchant $merchant
 */
#[Fillable([
    'merchant_id', 'livemode', 'title', 'description', 'amount', 'min_amount', 'max_amount', 'currency',
    'is_active', 'max_uses', 'collect_phone', 'collect_email', 'expires_at', 'created_by',
])]
class PaymentLink extends Model
{
    /** @use HasFactory<PaymentLinkFactory> */
    use BelongsToMerchant, HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'livemode' => 'boolean',
            'amount' => 'integer',
            'min_amount' => 'integer',
            'max_amount' => 'integer',
            'is_active' => 'boolean',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'collect_phone' => 'boolean',
            'collect_email' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'pl_';
    }

    protected function publicIdLength(): int
    {
        return 16;
    }

    /**
     * @return HasMany<CheckoutSession, $this>
     */
    public function checkoutSessions(): HasMany
    {
        return $this->hasMany(CheckoutSession::class);
    }

    public function hasFixedAmount(): bool
    {
        return $this->amount !== null;
    }

    public function isAcceptingPayments(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return $this->max_uses === null || $this->uses_count < $this->max_uses;
    }

    public function url(): string
    {
        return route('links.show', $this);
    }
}
