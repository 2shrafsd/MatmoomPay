<?php

declare(strict_types=1);

namespace App\Actions\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Merchant;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

/**
 * Records a webhook event for the merchant's endpoint and queues its delivery after the
 * surrounding transaction commits. Does nothing when the merchant has no active endpoint.
 */
final class QueueWebhook
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Merchant $merchant, bool $livemode, WebhookEvent $event, array $data): ?WebhookDelivery
    {
        $endpoint = WebhookEndpoint::query()
            ->forAccount($merchant, $livemode)
            ->where('is_active', true)
            ->first();

        if ($endpoint === null) {
            return null;
        }

        $delivery = WebhookDelivery::create([
            'merchant_id' => $merchant->id,
            'webhook_endpoint_id' => $endpoint->id,
            'livemode' => $livemode,
            'event_type' => $event->value,
            // The event ID survives retries and manual resends, so merchants can drop duplicates; created_at lets
            // them ignore an event older than what they already processed.
            'payload' => json_encode([
                'id' => 'evt_'.Str::random(24),
                'event_type' => $event->value,
                'created_at' => now()->toIso8601String(),
                'livemode' => $livemode,
                'data' => $data,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'status' => WebhookDeliveryStatus::Pending,
        ]);

        DeliverWebhook::dispatch($delivery)->afterCommit();

        return $delivery;
    }
}
