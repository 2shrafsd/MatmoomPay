<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Flat key/value metadata: up to 50 keys of 40 characters, scalar values up to 500 characters.
 */
class Metadata implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('The :attribute must be an object of key/value pairs.');

            return;
        }

        if (count($value) > (int) config('gateway.limits.max_metadata_keys')) {
            $fail('The :attribute may not have more than '.config('gateway.limits.max_metadata_keys').' keys.');

            return;
        }

        foreach ($value as $key => $item) {
            if (mb_strlen((string) $key) > 40) {
                $fail('Each :attribute key may not be longer than 40 characters.');

                return;
            }

            if ($item !== null && ! is_scalar($item)) {
                $fail('Each :attribute value must be a string, number or boolean.');

                return;
            }

            if (is_string($item) && mb_strlen($item) > 500) {
                $fail('Each :attribute value may not be longer than 500 characters.');

                return;
            }
        }
    }
}
