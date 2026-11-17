<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Customer;
use App\Models\PaymentLink;
use App\Models\Plan;

final readonly class CheckoutSessionData
{
    /**
     * @param  list<array{name: string, quantity: int, unit_amount: int}>  $products
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $clientReferenceId,
        public array $products,
        public string $successUrl,
        public string $cancelUrl,
        public array $metadata = [],
        public ?Customer $customer = null,
        public bool $saveCardOnSuccess = false,
        public ?int $expireInMinutes = null,
        public ?PaymentLink $paymentLink = null,
        public ?Plan $plan = null,
    ) {}

    public function mode(): string
    {
        return $this->plan === null ? 'payment' : 'subscription';
    }
}
