<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WebhookDeliveryStatus;
use App\Models\Concerns\BelongsToMerchant;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property int|null $webhook_endpoint_id
 * @property bool $livemode
 * @property string $event_type
 * @property string $payload
 * @property WebhookDeliveryStatus $status
 * @property int $attempts
 * @property int|null $response_status
 * @property string|null $response_excerpt
 * @property string|null $last_error
 * @property CarbonImmutable|null $next_attempt_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $created_at
 * @property-read WebhookEndpoint|null $endpoint
 */
#[Fillable([
    'merchant_id', 'webhook_endpoint_id', 'livemode', 'event_type', 'payload', 'status', 'attempts',
    'response_status', 'response_excerpt', 'last_error', 'next_attempt_at', 'delivered_at',
])]
class WebhookDelivery extends Model
{
    use BelongsToMerchant, HasPublicId, MassPrunable;

    /**
     * Payloads hold customer data; delivered or failed events are kept for 90 days of debugging, then removed.
     *
     * @return Builder<WebhookDelivery>
     */
    public function prunable(): Builder
    {
        return self::query()
            ->where('created_at', '<', now()->subDays((int) config('gateway.webhooks.retention_days')))
            ->where('status', '!=', WebhookDeliveryStatus::Pending);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'livemode' => 'boolean',
            'status' => WebhookDeliveryStatus::class,
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'whd_';
    }

    /**
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
