<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

class StoreCustomerRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_customer_id' => ['required', 'string', 'max:191'],
        ];
    }
}
