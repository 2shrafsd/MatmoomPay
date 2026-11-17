<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case InProcess = 'InProcess';
    case Successful = 'Successful';
    case Failed = 'Failed';

    public function label(): string
    {
        return match ($this) {
            self::InProcess => __('In process'),
            self::Successful => __('Successful'),
            self::Failed => __('Failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InProcess => 'amber',
            self::Successful => 'green',
            self::Failed => 'red',
        };
    }
}
