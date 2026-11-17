<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Generates non-enumerable identifiers that are safe to expose in URLs and API payloads.
 */
final class PublicId
{
    public static function make(string $prefix, int $length = 24): string
    {
        return $prefix.Str::random($length);
    }

    /**
     * A numeric identifier such as a payment id or invoice number: a date stamp plus random digits.
     */
    public static function numeric(int $randomDigits = 6): string
    {
        $random = '';

        for ($i = 0; $i < $randomDigits; $i++) {
            $random .= (string) random_int(0, 9);
        }

        return now()->format('ymdHis').$random;
    }
}
