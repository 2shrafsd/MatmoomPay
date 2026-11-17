<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentIntentStatus: string
{
    case RequiresPaymentMethod = 'requires_payment_method';
    case RequiresConfirmation = 'requires_confirmation';
    case RequiresAction = 'requires_action';
    case Succeeded = 'succeeded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::RequiresPaymentMethod => __('Requires payment method'),
            self::RequiresConfirmation => __('Requires confirmation'),
            self::RequiresAction => __('Requires authentication'),
            self::Succeeded => __('Succeeded'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Succeeded => 'green',
            self::Cancelled => 'zinc',
            default => 'amber',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Succeeded, self::Cancelled], true);
    }
}
