<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Formats integer amounts in the currency's smallest unit in use (whole pounds for SDG). Amounts are never stored as floats.
 */
final class Money
{
    public static function format(int $minorUnits, ?string $currency = null): string
    {
        $currency ??= (string) config('gateway.currency');

        return $currency.' '.self::decimal($minorUnits);
    }

    public static function decimal(int $minorUnits): string
    {
        $decimals = (int) config('gateway.currency_decimals');
        $negative = $minorUnits < 0;
        $value = number_format(abs($minorUnits) / (10 ** $decimals), $decimals, '.', ',');

        return ($negative ? '-' : '').$value;
    }

    /**
     * Converts a user-entered major-unit string such as "12.5" into minor units without float rounding errors.
     */
    public static function toMinorUnits(string $amount): int
    {
        $decimals = (int) config('gateway.currency_decimals');
        $amount = trim($amount);

        if (! preg_match(self::amountPattern(), $amount)) {
            throw new \InvalidArgumentException('Invalid amount format.');
        }

        [$whole, $fraction] = array_pad(explode('.', str_replace(',', '', $amount), 2), 2, '');

        return ((int) $whole * (10 ** $decimals)) + (int) str_pad($fraction, $decimals, '0');
    }

    /**
     * Validation regex for a major-unit amount typed by a person, matching the currency's decimals.
     */
    public static function amountPattern(): string
    {
        $decimals = (int) config('gateway.currency_decimals');

        $whole = '(\d{1,12}|\d{1,3}(,\d{3}){1,3})';

        return $decimals === 0
            ? '/^'.$whole.'$/'
            : '/^'.$whole.'(\.\d{1,'.$decimals.'})?$/';
    }

    public static function amountRule(): string
    {
        return 'regex:'.self::amountPattern();
    }

    /**
     * A friendly example such as "1,250.00" for hints and error messages.
     */
    public static function example(): string
    {
        return self::decimal(18_500 * (10 ** (int) config('gateway.currency_decimals')));
    }

    public static function currency(): string
    {
        return (string) config('gateway.currency');
    }

    /**
     * Percentage (rounded half up) plus the fixed part, never below the platform minimum: with whole pounds a
     * small payment would otherwise round to a zero fee, and splitting a basket would avoid fees entirely.
     */
    public static function fee(int $amount, int $rateBps, int $fixed): int
    {
        $fee = intdiv($amount * $rateBps + 5_000, 10_000) + $fixed;

        return $rateBps === 0 && $fixed === 0 ? 0 : max($fee, (int) config('gateway.fees.minimum'));
    }

    public static function minimumAmount(): int
    {
        return (int) config('gateway.limits.min_amount');
    }

    public static function belowMinimumMessage(): string
    {
        return 'The amount must be at least '.self::format(self::minimumAmount()).'.';
    }
}
