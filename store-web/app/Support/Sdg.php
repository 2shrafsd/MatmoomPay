<?php

namespace App\Support;

/**
 * Prices are whole Sudanese pounds, the same integers the gateway expects.
 */
class Sdg
{
    public static function format(int $pounds): string
    {
        return number_format($pounds).' ج.س';
    }
}
