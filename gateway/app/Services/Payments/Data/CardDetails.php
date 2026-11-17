<?php

declare(strict_types=1);

namespace App\Services\Payments\Data;

use LogicException;
use SensitiveParameter;

/**
 * Raw card data in transit to a processor. It is never persisted, logged, queued or serialized.
 */
final readonly class CardDetails
{
    public function __construct(
        #[SensitiveParameter] public string $number,
        public int $expiryMonth,
        public int $expiryYear,
        #[SensitiveParameter] public string $cvc,
        public string $holderName,
    ) {}

    public function bin(): string
    {
        return substr($this->number, 0, 6);
    }

    public function lastFour(): string
    {
        return substr($this->number, -4);
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['number' => $this->bin().'******'.$this->lastFour(), 'cvc' => '***'];
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new LogicException('Card details must never be serialized.');
    }
}
