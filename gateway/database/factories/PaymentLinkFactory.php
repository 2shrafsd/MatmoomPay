<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\PaymentLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentLink>
 */
class PaymentLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'livemode' => false,
            'title' => fake()->randomElement(['Sudanese perfume gift box', 'Jebena coffee for two', 'Workshop seat', 'Delivery fee']),
            'description' => null,
            'amount' => 12_500,
            'currency' => 'SDG',
            'is_active' => true,
            'collect_phone' => false,
            'collect_email' => false,
        ];
    }

    public function openAmount(?int $min = 1_000, ?int $max = 100_000): static
    {
        return $this->state(fn () => ['amount' => null, 'min_amount' => $min, 'max_amount' => $max]);
    }
}
