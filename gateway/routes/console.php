<?php

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Jobs\RenewSubscriptionJob;
use App\Models\IdempotencyKey;
use App\Models\Subscription;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('gateway:prune-idempotency-keys', function () {
    $deleted = IdempotencyKey::query()->where('expires_at', '<', now())->delete();

    $this->info("Deleted {$deleted} expired idempotency keys.");
})->purpose('Delete idempotency keys past their 24 hour window');

Schedule::command('gateway:prune-idempotency-keys')->daily()->onOneServer();

Artisan::command('gateway:bill-subscriptions', function () {
    $count = 0;

    Subscription::query()
        ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
        ->where('current_period_end', '<=', now())
        ->whereDoesntHave('invoices', fn ($query) => $query->where('status', InvoiceStatus::Open)->where('next_payment_attempt', '>', now()))
        ->chunkById(200, function ($subscriptions) use (&$count) {
            foreach ($subscriptions as $subscription) {
                RenewSubscriptionJob::dispatch($subscription);
                $count++;
            }
        });

    $this->info("Queued {$count} subscription renewals.");
})->purpose('Queue renewal charges for subscriptions whose billing period has ended');

Schedule::command('gateway:bill-subscriptions')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();

Schedule::command('gateway:reconcile')->everyMinute()->withoutOverlapping()->onOneServer();

Schedule::command('model:prune')->daily()->onOneServer();
Schedule::command('queue:prune-failed', ['--hours' => 24 * 30])->daily()->onOneServer();
Schedule::command('auth:clear-resets')->everyFifteenMinutes()->onOneServer();
