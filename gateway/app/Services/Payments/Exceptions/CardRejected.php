<?php

declare(strict_types=1);

namespace App\Services\Payments\Exceptions;

use RuntimeException;

final class CardRejected extends RuntimeException
{
    public static function unsupportedBrand(): self
    {
        return new self('Only Visa and Mastercard cards are accepted.');
    }
}
