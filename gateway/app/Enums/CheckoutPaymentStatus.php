<?php

declare(strict_types=1);

namespace App\Enums;

enum CheckoutPaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => __('Unpaid'),
            self::Paid => __('Paid'),
            self::Cancelled => __('Cancelled'),
            self::Expired => __('Expired'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'amber',
            self::Paid => 'green',
            self::Cancelled => 'zinc',
            self::Expired => 'zinc',
        };
    }
}
