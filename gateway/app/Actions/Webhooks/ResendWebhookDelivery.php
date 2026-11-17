<?php

declare(strict_types=1);

namespace App\Actions\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;

final class ResendWebhookDelivery
{
    public function handle(WebhookDelivery $delivery): void
    {
        $delivery->update([
            'status' => WebhookDeliveryStatus::Pending,
            'next_attempt_at' => null,
        ]);

        DeliverWebhook::dispatch($delivery);
    }
}
