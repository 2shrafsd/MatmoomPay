<?php

declare(strict_types=1);

namespace App\Enums;

enum SettlementStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Awaiting transfer'),
            self::Paid => __('Transferred'),
            self::Failed => __('Transfer failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Paid => 'green',
            self::Failed => 'red',
        };
    }
}
