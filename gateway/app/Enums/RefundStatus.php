<?php

declare(strict_types=1);

namespace App\Enums;

enum RefundStatus: string
{
    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Successful => __('Successful'),
            self::Failed => __('Failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Successful => 'green',
            self::Failed => 'red',
        };
    }
}
