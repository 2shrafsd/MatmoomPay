<?php

namespace Database\Factories;

use App\Enums\PlanInterval;
use App\Models\Merchant;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'livemode' => false,
            'name' => fake()->randomElement(['Gold membership', 'Coffee club', 'Monthly spices box']),
            'amount' => 15_000,
            'currency' => 'SDG',
            'interval' => PlanInterval::Month,
            'interval_count' => 1,
            'is_active' => true,
        ];
    }
}
