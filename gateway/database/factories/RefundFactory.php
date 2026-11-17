<?php

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'merchant_id' => fn (array $attributes) => Payment::query()->whereKey($attributes['payment_id'])->value('merchant_id'),
            'livemode' => fn (array $attributes) => (bool) Payment::query()->whereKey($attributes['payment_id'])->value('livemode'),
            'amount' => 1_000,
            'reason' => 'Customer changed their mind',
            'metadata' => [],
            'status' => RefundStatus::Successful,
        ];
    }
}
