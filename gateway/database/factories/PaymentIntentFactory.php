<?php

namespace Database\Factories;

use App\Enums\PaymentIntentStatus;
use App\Models\Merchant;
use App\Models\PaymentIntent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentIntent>
 */
class PaymentIntentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'livemode' => false,
            'client_reference_id' => 'sub-'.fake()->unique()->numberBetween(1000, 999999),
            'amount' => 5_000,
            'currency' => 'SDG',
            'return_url' => 'https://shop.example/return',
            'metadata' => [],
            'status' => PaymentIntentStatus::RequiresPaymentMethod,
            'expire_at' => now()->addMinutes(30),
        ];
    }
}
