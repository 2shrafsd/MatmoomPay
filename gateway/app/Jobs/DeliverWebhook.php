<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Notifications\NotifyMerchantTeam;
use App\Actions\Webhooks\SendWebhook;
use App\Enums\TeamNotification;
use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Notifications\WebhookEndpointFailing;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;

/**
 * Delivers one webhook with exponential back-off between attempts (about a day in total).
 *
 * Unique per delivery: while one retry chain is alive (including its back-off waits), resends and the stuck-delivery
 * sweeper cannot start a second chain, so a backlog is never multiplied.
 */
#[DeleteWhenMissingModels]
class DeliverWebhook implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Seconds to wait before each retry.
     *
     * @var list<int>
     */
    public const BACKOFF = [30, 120, 600, 1800, 3600, 7200, 21600, 43200];

    /**
     * Seconds before a stuck HTTP call is abandoned (the request itself times out after 10).
     */
    public int $timeout = 30;

    /**
     * Longer than the whole back-off schedule, so the lock outlives a live chain but frees a lost one within a day.
     */
    public int $uniqueFor = 86400;

    public function __construct(public WebhookDelivery $delivery) {}

    public function uniqueId(): string
    {
        return $this->delivery->public_id;
    }

    public function tries(): int
    {
        return (int) config('gateway.webhooks.max_attempts');
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        // A duplicate copy of this delivery is simply dropped: the copy already running owns the retries,
        // so it must not burn one of this delivery's attempts.
        return [(new WithoutOverlapping($this->delivery->public_id))->dontRelease()->expireAfter(120)];
    }

    public function handle(SendWebhook $sendWebhook): void
    {
        if ($this->delivery->status === WebhookDeliveryStatus::Delivered) {
            return;
        }

        if ($sendWebhook->handle($this->delivery)) {
            return;
        }

        if ($this->attempts() >= $this->tries()) {
            $this->markFailed();

            return;
        }

        $delay = self::BACKOFF[min($this->attempts() - 1, count(self::BACKOFF) - 1)];

        $this->delivery->fill(['next_attempt_at' => now()->addSeconds($delay)])->save();
        $this->release($delay);
    }

    public function failed(): void
    {
        $this->markFailed();
    }

    private function markFailed(): void
    {
        $this->delivery->fill([
            'status' => WebhookDeliveryStatus::Failed,
            'next_attempt_at' => null,
        ])->save();

        // At most one email per endpoint a day, however many events pile up behind a broken endpoint.
        if (Cache::add('webhook-failing-notice:'.$this->delivery->webhook_endpoint_id, true, now()->addDay())) {
            app(NotifyMerchantTeam::class)->handle($this->delivery->merchant, TeamNotification::WebhookFailing, new WebhookEndpointFailing($this->delivery));
        }
    }
}
