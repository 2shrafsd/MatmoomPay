<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentMethod
 */
class PaymentMethodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'bin' => (int) $this->bin,
            'masked_card' => $this->maskedCard(),
            'expiry_month' => $this->expiry_month,
            'expiry_year' => $this->expiry_year,
            'nickname' => $this->nickname,
            'brand' => $this->brand->value,
            'card_type' => $this->card_type->value,
        ];
    }
}
