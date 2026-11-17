<?php

declare(strict_types=1);

namespace App\Services\Payments\Data;

final readonly class RefundResult
{
    public function __construct(
        public bool $successful,
        public string $processorReference,
        public ?string $failureReason = null,
    ) {}
}
