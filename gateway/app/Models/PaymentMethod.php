<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CardBrand;
use App\Enums\CardType;
use App\Models\Concerns\BelongsToMerchant;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved card. Only the processor token (encrypted), BIN, last four digits and expiry are stored;
 * the full card number and CVV never reach the database.
 *
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property bool $livemode
 * @property int $customer_id
 * @property string $processor
 * @property string $processor_token
 * @property string $fingerprint
 * @property string $bin
 * @property string $last_four
 * @property CardBrand $brand
 * @property CardType $card_type
 * @property int $expiry_month
 * @property int $expiry_year
 * @property string|null $nickname
 * @property CarbonImmutable|null $created_at
 * @property-read Customer $customer
 */
#[Fillable([
    'merchant_id', 'livemode', 'customer_id', 'processor', 'processor_token', 'fingerprint', 'bin',
    'last_four', 'brand', 'card_type', 'expiry_month', 'expiry_year', 'nickname',
])]
#[Hidden(['processor_token', 'fingerprint'])]
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use BelongsToMerchant, HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'livemode' => 'boolean',
            'processor_token' => 'encrypted',
            'brand' => CardBrand::class,
            'card_type' => CardType::class,
            'expiry_month' => 'integer',
            'expiry_year' => 'integer',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'card_';
    }

    protected function publicIdLength(): int
    {
        return 28;
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function maskedCard(): string
    {
        return 'XXXX XXXX XXXX '.$this->last_four;
    }

    public function isExpired(): bool
    {
        return now()->startOfMonth()->greaterThan(CarbonImmutable::create($this->expiry_year, $this->expiry_month, 1));
    }
}
