<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SettlementStatus;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property string $currency
 * @property int $gross_amount
 * @property int $fee_amount
 * @property int $refund_amount
 * @property int $net_amount
 * @property int $transactions_count
 * @property SettlementStatus $status
 * @property CarbonImmutable|null $period_start
 * @property CarbonImmutable|null $period_end
 * @property string|null $bank_name
 * @property string|null $account_name
 * @property string|null $iban
 * @property string|null $bank_reference
 * @property string|null $failure_reason
 * @property CarbonImmutable|null $paid_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property-read Merchant $merchant
 */
#[Fillable([
    'merchant_id', 'currency', 'gross_amount', 'fee_amount', 'refund_amount', 'net_amount', 'transactions_count',
    'status', 'period_start', 'period_end', 'bank_reference', 'paid_at', 'created_by',
    'bank_name', 'account_name', 'iban', 'failure_reason',
])]
#[Hidden(['iban'])]
class Settlement extends Model
{
    use HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_amount' => 'integer',
            'fee_amount' => 'integer',
            'refund_amount' => 'integer',
            'net_amount' => 'integer',
            'transactions_count' => 'integer',
            'status' => SettlementStatus::class,
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'paid_at' => 'datetime',
            'iban' => 'encrypted',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'stl_';
    }

    protected function publicIdLength(): int
    {
        return 16;
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class)->withTrashed();
    }

    /**
     * @return HasMany<BalanceTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(BalanceTransaction::class);
    }

    public function maskedIban(): string
    {
        return blank($this->iban) ? '—' : str_repeat('•', 4).' '.substr((string) $this->iban, -4);
    }
}
