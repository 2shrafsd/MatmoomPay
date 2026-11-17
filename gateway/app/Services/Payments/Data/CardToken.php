<?php

declare(strict_types=1);

namespace App\Services\Payments\Data;

use App\Enums\CardBrand;
use App\Enums\CardType;

final readonly class CardToken
{
    public function __construct(
        public string $token,
        public string $fingerprint,
        public string $bin,
        public string $lastFour,
        public CardBrand $brand,
        public CardType $type,
        public int $expiryMonth,
        public int $expiryYear,
    ) {}

    public function maskedCard(): string
    {
        return substr($this->bin, 0, 4).' '.substr($this->bin, 4, 2).'XX XXXX '.$this->lastFour;
    }
}
