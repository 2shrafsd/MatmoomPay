<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

class ListRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.config('gateway.limits.max_page_size')],
            'skip' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'customer_id' => ['sometimes', 'string', 'max:40'],
            'checkout_invoice' => ['sometimes', 'string', 'max:24'],
            'payment_intent' => ['sometimes', 'string', 'max:40'],
        ];
    }

    public function limit(): int
    {
        return (int) $this->validated('limit', 10);
    }

    public function skip(): int
    {
        return (int) $this->validated('skip', 0);
    }
}
