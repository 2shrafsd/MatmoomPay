<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Arabic and Persian keyboards type ٠-٩ / ۰-۹. Card numbers, codes, amounts and phone numbers must accept them.
 */
final class Digits
{
    private const ARABIC = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٬', '٫'];

    private const LATIN = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', ',', '.'];

    public static function toLatin(string $value): string
    {
        return str_replace(self::ARABIC, self::LATIN, $value);
    }
}
