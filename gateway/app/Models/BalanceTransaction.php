<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BalanceTransactionType;
use App\Models\Concerns\BelongsToMerchant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only ledger entry. Amounts are signed whole pounds: credits are positive, debits negative.
 *
 * @property int $id
 * @property int $merchant_id
 * @property bool $livemode
 * @property BalanceTransactionType $type
 * @property int $amount
 * @property string $currency
 * @property string $source_type
 * @property int $source_id
 * @property int|null $settlement_id
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['merchant_id', 'livemode', 'type', 'amount', 'currency', 'source_type', 'source_id', 'description'])]
class BalanceTransaction extends Model
{
    use BelongsToMerchant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'livemode' => 'boolean',
            'type' => BalanceTransactionType::class,
            'amount' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Settlement, $this>
     */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }
}
