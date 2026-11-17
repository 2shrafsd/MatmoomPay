<?php

declare(strict_types=1);

namespace App\Services\Payments\Data;

final readonly class ChargeRequest
{
    /**
     * @param  bool  $customerPresent  true when the cardholder is on the hosted page and can complete a challenge
     */
    public function __construct(
        public string $token,
        public int $amount,
        public string $currency,
        public string $reference,
        public bool $customerPresent,
    ) {}
}
