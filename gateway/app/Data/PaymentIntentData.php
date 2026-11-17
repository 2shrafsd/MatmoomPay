<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\PaymentMethod;

final readonly class PaymentIntentData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public int $amount,
        public string $clientReferenceId,
        public string $returnUrl,
        public array $metadata = [],
        public ?PaymentMethod $paymentMethod = null,
        public ?int $expireInMinutes = null,
    ) {}
}
