<?php

declare(strict_types=1);

namespace App\Enums;

enum MerchantStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Rejected = 'rejected';
    case Banned = 'banned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Under review'),
            self::Active => __('Active'),
            self::Suspended => __('Suspended'),
            self::Rejected => __('Rejected'),
            self::Banned => __('Banned'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Active => 'green',
            self::Suspended => 'red',
            self::Rejected => 'zinc',
            self::Banned => 'red',
        };
    }

    public function canProcessLivePayments(): bool
    {
        return $this === self::Active;
    }

    public function canUseTestMode(): bool
    {
        return in_array($this, [self::Pending, self::Active], true);
    }

    /**
     * A banned merchant is shut out completely: no API, no payments and no portal for its team.
     */
    public function blocksPortal(): bool
    {
        return $this === self::Banned;
    }

    /**
     * Stopping a merchant (suspend, reject, ban) is shown to the merchant, so it needs a written reason.
     */
    public function requiresReason(): bool
    {
        return in_array($this, [self::Suspended, self::Rejected, self::Banned], true);
    }
}
