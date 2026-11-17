<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RefundStatus;
use App\Models\Concerns\BelongsToMerchant;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property bool $livemode
 * @property int $payment_id
 * @property int $amount
 * @property string $reason
 * @property array<string, mixed>|null $metadata
 * @property RefundStatus $status
 * @property string|null $processor_reference
 * @property string|null $failure_reason
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property-read Payment $payment
 */
#[Fillable([
    'merchant_id', 'livemode', 'payment_id', 'amount', 'reason', 'metadata', 'status', 'processor_reference',
    'failure_reason', 'created_by',
])]
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
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
            'status' => RefundStatus::class,
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'ref_';
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
