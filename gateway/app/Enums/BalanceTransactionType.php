<?php

declare(strict_types=1);

namespace App\Enums;

enum BalanceTransactionType: string
{
    case Payment = 'payment';
    case Fee = 'fee';
    case Refund = 'refund';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Payment => __('Payment'),
            self::Fee => __('Processing fee'),
            self::Refund => __('Refund'),
            self::Adjustment => __('Adjustment'),
        };
    }
}
