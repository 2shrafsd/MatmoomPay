<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonInterface;

final readonly class PaymentLinkData
{
    public function __construct(
        public string $title,
        public ?string $description,
        public ?int $amount,
        public ?int $minAmount,
        public ?int $maxAmount,
        public ?int $maxUses,
        public bool $collectPhone,
        public bool $collectEmail,
        public ?CarbonInterface $expiresAt,
    ) {}
}
