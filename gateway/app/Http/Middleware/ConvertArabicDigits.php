<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Digits;
use Illuminate\Foundation\Http\Middleware\TransformsRequest;

/**
 * Lets payers and merchants type card numbers, codes and amounts with an Arabic keyboard. Passwords are
 * left exactly as typed.
 */
class ConvertArabicDigits extends TransformsRequest
{
    protected function transform($key, $value): mixed
    {
        if (! is_string($value) || str_contains((string) $key, 'password')) {
            return $value;
        }

        return Digits::toLatin($value);
    }
}
