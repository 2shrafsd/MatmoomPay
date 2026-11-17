<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\BalanceTransaction;
use App\Models\Payment;
use App\Models\Refund;
use App\Support\GatewayAccount;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only aggregates for the merchant dashboard. Every query is scoped to one merchant and mode.
 */
final readonly class AccountMetrics
{
    public function __construct(private GatewayAccount $account) {}

    public function volume(CarbonInterface $from): int
    {
        return (int) $this->payments()->where('status', PaymentStatus::Successful)->where('completed_at', '>=', $from)->sum('amount');
    }

    public function successfulCount(CarbonInterface $from): int
    {
        return $this->payments()->where('status', PaymentStatus::Successful)->where('created_at', '>=', $from)->count();
    }

    /**
     * Share of finished attempts that succeeded, as a whole percentage, or null without attempts.
     */
    public function successRate(CarbonInterface $from): ?int
    {
        $finished = $this->payments()->where('status', '!=', PaymentStatus::InProcess)->where('created_at', '>=', $from)->count();

        return $finished === 0 ? null : (int) round($this->successfulCount($from) / $finished * 100);
    }

    public function refunded(CarbonInterface $from): int
    {
        return (int) Refund::query()
            ->forAccount($this->account->merchant, $this->account->livemode)
            ->where('status', RefundStatus::Successful)
            ->where('created_at', '>=', $from)
            ->sum('amount');
    }

    /**
     * Net of every ledger entry not yet included in a settlement.
     */
    public function availableBalance(): int
    {
        return (int) BalanceTransaction::query()
            ->forAccount($this->account->merchant, $this->account->livemode)
            ->whereNull('settlement_id')
            ->sum('amount');
    }

    /**
     * Successful volume per day for the last $days days, oldest first, including empty days.
     *
     * @return list<array{date: CarbonImmutable, amount: int}>
     */
    public function dailyVolume(int $days = 14): array
    {
        $from = now()->startOfDay()->subDays($days - 1);

        $totals = $this->payments()
            ->where('status', PaymentStatus::Successful)
            ->where('completed_at', '>=', $from)
            ->selectRaw('DATE(completed_at) as day, SUM(amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $date = CarbonImmutable::parse($from)->addDays($i);
            $series[] = ['date' => $date, 'amount' => (int) ($totals[$date->toDateString()] ?? 0)];
        }

        return $series;
    }

    /**
     * @return Builder<Payment>
     */
    private function payments(): Builder
    {
        return Payment::query()->forAccount($this->account->merchant, $this->account->livemode);
    }
}
