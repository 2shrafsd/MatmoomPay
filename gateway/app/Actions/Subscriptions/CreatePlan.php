<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\PlanInterval;
use App\Models\Plan;
use App\Models\User;
use App\Support\GatewayAccount;

final readonly class CreatePlan
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(GatewayAccount $account, string $name, int $amount, PlanInterval $interval, int $intervalCount, ?User $actor = null): Plan
    {
        $plan = Plan::create([
            'merchant_id' => $account->merchant->id,
            'livemode' => $account->livemode,
            'name' => $name,
            'amount' => $amount,
            'currency' => (string) config('gateway.currency'),
            'interval' => $interval,
            'interval_count' => $intervalCount,
            'is_active' => true,
        ]);

        if ($actor !== null) {
            $this->recordAuditLog->handle($actor, 'plan.created', $account->merchant, $plan);
        }

        return $plan;
    }
}
