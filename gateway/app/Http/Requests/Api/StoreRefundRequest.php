<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Exceptions\GatewayException;
use App\Models\Payment;
use App\Rules\Metadata;

class StoreRefundRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_id' => ['required', 'string', 'max:32'],
            'reason' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'integer', 'min:1'],
            'metadata' => ['present', new Metadata],
        ];
    }

    public function payment(): Payment
    {
        return Payment::query()
            ->forAccount($this->account()->merchant, $this->account()->livemode)
            ->where('public_id', $this->validated('payment_id'))
            ->first() ?? throw GatewayException::notFound();
    }
}
