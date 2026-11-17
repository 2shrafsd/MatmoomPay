<?php

declare(strict_types=1);

namespace App\Services\Payments\Data;

/**
 * An authenticated answer from the bank's payment page.
 */
final readonly class HostedPaymentResult
{
    public function __construct(
        public string $reference,
        public int $amount,
        public string $currency,
        public bool $approved,
        public string $processorReference,
        public ?CardToken $card,
        public ?string $declineReason = null,
    ) {}
}
