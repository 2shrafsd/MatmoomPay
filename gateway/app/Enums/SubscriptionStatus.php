<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::PastDue => __('Payment overdue'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::PastDue => 'amber',
            self::Cancelled => 'zinc',
        };
    }

    public function isBillable(): bool
    {
        return $this !== self::Cancelled;
    }
}
