<?php

declare(strict_types=1);

namespace App\Enums;

enum CardBrand: string
{
    case Visa = 'Visa';
    case MasterCard = 'MasterCard';

    public static function fromNumber(string $number): ?self
    {
        return match (true) {
            str_starts_with($number, '4') => self::Visa,
            (bool) preg_match('/^(5[1-5]|2[2-7])/', $number) => self::MasterCard,
            default => null,
        };
    }
}
