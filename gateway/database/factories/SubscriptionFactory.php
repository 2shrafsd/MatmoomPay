<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'merchant_id' => fn (array $attributes) => Plan::query()->whereKey($attributes['plan_id'])->value('merchant_id'),
            'livemode' => false,
            'customer_id' => fn (array $attributes) => Customer::factory()->create(['merchant_id' => $attributes['merchant_id']])->id,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subMinute(),
        ];
    }
}
