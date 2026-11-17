<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\PlanInterval;
use Illuminate\Validation\Rule;

class StorePlanRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'integer', 'min:100', 'max:'.config('gateway.limits.max_total_amount')],
            'interval' => ['required', Rule::enum(PlanInterval::class)],
            'interval_count' => ['sometimes', 'integer', 'min:1', 'max:12'],
        ];
    }
}
