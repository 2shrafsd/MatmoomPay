<?php

declare(strict_types=1);

namespace App\Services\Payments\Data;

final readonly class HostedPaymentRedirect
{
    /**
     * @param  'GET'|'POST'  $method
     * @param  array<string, string>  $fields  form fields to auto-post when the bank expects a POST
     */
    public function __construct(
        public string $url,
        public string $method = 'GET',
        public array $fields = [],
    ) {}
}
