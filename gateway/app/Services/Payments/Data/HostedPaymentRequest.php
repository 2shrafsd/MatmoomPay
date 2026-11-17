<?php

declare(strict_types=1);

namespace App\Services\Payments\Data;

final readonly class HostedPaymentRequest
{
    public function __construct(
        public string $reference,
        public int $amount,
        public string $currency,
        public string $description,
        public string $returnUrl,
        public string $cancelUrl,
        public bool $saveCard,
        public string $locale,
    ) {}
}
