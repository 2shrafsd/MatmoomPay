<?php

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Jobs\RenewSubscriptionJob;
use App\Models\IdempotencyKey;
use App\Models\Subscription;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

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

Artisan::command('gateway:purge-temporary-uploads', function () {
    $disk = Storage::disk(config('livewire.temporary_file_upload.disk') ?: config('filesystems.default'));
    $directory = config('livewire.temporary_file_upload.directory') ?: 'livewire-tmp';
    $cutoff = now()->subDay()->getTimestamp();
    $deleted = 0;

    foreach ($disk->files($directory) as $path) {
        if ($disk->lastModified($path) < $cutoff) {
            $disk->delete($path);
            $deleted++;
        }
    }

    $this->info("Deleted {$deleted} abandoned temporary uploads.");
})->purpose('Delete Livewire temporary uploads older than a day, which Livewire only cleans up on the next upload');

Schedule::command('gateway:purge-temporary-uploads')->hourly()->onOneServer();

Schedule::command('model:prune')->daily()->onOneServer();
Schedule::command('queue:prune-failed', ['--hours' => 24 * 30])->daily()->onOneServer();
Schedule::command('auth:clear-resets')->everyFifteenMinutes()->onOneServer();
