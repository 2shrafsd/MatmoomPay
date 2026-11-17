<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToMerchant;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One redirect to the bank's payment page. Its public id is the reference the bank echoes back,
 * and it can complete exactly once.
 *
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property bool $livemode
 * @property int $checkout_session_id
 * @property int|null $payment_id
 * @property string $processor
 * @property int $amount
 * @property string $currency
 * @property bool $save_card
 * @property string $status
 * @property CarbonImmutable $expires_at
 * @property-read CheckoutSession $checkoutSession
 * @property-read Payment|null $payment
 */
#[Fillable(['merchant_id', 'livemode', 'checkout_session_id', 'payment_id', 'processor', 'amount', 'currency', 'save_card', 'status', 'expires_at'])]
class HostedPaymentAttempt extends Model
{
    use BelongsToMerchant, HasPublicId;

    public const PENDING = 'pending';

    public const COMPLETED = 'completed';

    public const SUPERSEDED = 'superseded';

    public const EXPIRED = 'expired';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'livemode' => 'boolean',
            'amount' => 'integer',
            'save_card' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'hpa_';
    }

    protected function publicIdLength(): int
    {
        return 28;
    }

    /**
     * @return BelongsTo<CheckoutSession, $this>
     */
    public function checkoutSession(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
