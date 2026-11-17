<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\SubscriptionStatus;
use App\Exceptions\GatewayException;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CancelSubscription
{
    public function __construct(
        private RecordInvoiceOutcome $recordInvoiceOutcome,
        private RecordAuditLog $recordAuditLog,
    ) {}

    public function handle(Subscription $subscription, bool $atPeriodEnd, ?User $actor = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $atPeriodEnd, $actor): Subscription {
            $subscription = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            if ($subscription->status === SubscriptionStatus::Cancelled) {
                throw GatewayException::cannotUpdate('This subscription is already cancelled.');
            }

            if ($atPeriodEnd) {
                $subscription->update(['cancel_at_period_end' => true]);
            } else {
                $this->recordInvoiceOutcome->cancel($subscription, 'Cancelled by the merchant.');
            }

            if ($actor !== null) {
                $this->recordAuditLog->handle($actor, 'subscription.cancelled', $subscription->merchant, $subscription, ['at_period_end' => $atPeriodEnd]);
            }

            return $subscription->refresh();
        });
    }
}
