<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonInterface;

enum PlanInterval: string
{
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    /**
     * The next period end. $anchorDay is the day of month the subscription started on, so a plan begun on the
     * 31st bills on the 28th in February and returns to the 31st in March instead of drifting to the 28th.
     */
    public function advance(CarbonInterface $from, int $count, ?int $anchorDay = null): CarbonInterface
    {
        $next = match ($this) {
            self::Week => $from->addWeeks($count),
            self::Month => $from->addMonthsNoOverflow($count),
            self::Year => $from->addYearsNoOverflow($count),
        };

        if ($this === self::Week || $anchorDay === null) {
            return $next;
        }

        return $next->day(min($anchorDay, $next->daysInMonth));
    }

    public function label(int $count = 1): string
    {
        return match ($this) {
            self::Week => trans_choice('{1} every week|{2} every 2 weeks|[3,*] every :count weeks', $count, ['count' => $count]),
            self::Month => trans_choice('{1} every month|{2} every 2 months|[3,*] every :count months', $count, ['count' => $count]),
            self::Year => trans_choice('{1} every year|{2} every 2 years|[3,*] every :count years', $count, ['count' => $count]),
        };
    }
}
