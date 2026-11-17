<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Console\Commands\ReconcilePayments;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Makes /up fail when the gateway cannot work: the database is unreachable or, in production, the
 * scheduler stopped (no reconciliation, renewals or expiry would run).
 */
final class CheckApplicationHealth
{
    public function handle(DiagnosingHealth $event): void
    {
        DB::select('select 1');

        $heartbeat = (int) Cache::get(ReconcilePayments::HEARTBEAT_KEY, 0);

        if (app()->isProduction() && $heartbeat < now()->subMinutes(5)->getTimestamp()) {
            throw new RuntimeException('The scheduler has not run in the last five minutes.');
        }
    }
}
