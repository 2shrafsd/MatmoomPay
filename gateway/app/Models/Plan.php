<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlanInterval;
use App\Models\Concerns\BelongsToMerchant;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property bool $livemode
 * @property string $name
 * @property int $amount
 * @property string $currency
 * @property PlanInterval $interval
 * @property int $interval_count
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['merchant_id', 'livemode', 'name', 'amount', 'currency', 'interval', 'interval_count', 'is_active'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use BelongsToMerchant, HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'livemode' => 'boolean',
            'amount' => 'integer',
            'interval' => PlanInterval::class,
            'interval_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'plan_';
    }

    protected function publicIdLength(): int
    {
        return 20;
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function billingLabel(): string
    {
        return $this->interval->label($this->interval_count);
    }
}
