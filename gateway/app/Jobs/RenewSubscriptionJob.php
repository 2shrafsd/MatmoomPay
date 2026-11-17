<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Subscriptions\RenewSubscription;
use App\Models\Subscription;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Support\Facades\Log;
use Throwable;

#[DeleteWhenMissingModels]
class RenewSubscriptionJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Longer than the 15 minute billing cycle so a slow renewal is never queued twice.
     */
    public int $uniqueFor = 3600;

    public function __construct(public Subscription $subscription) {}

    public function uniqueId(): string
    {
        return (string) $this->subscription->id;
    }

    public function handle(RenewSubscription $renewSubscription): void
    {
        $renewSubscription->handle($this->subscription);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('A subscription renewal crashed. The invoice waits an hour before the next attempt.', [
            'subscription' => $this->subscription->public_id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
