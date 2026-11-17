<?php

namespace Database\Factories;

use App\Enums\CheckoutPaymentStatus;
use App\Models\CheckoutSession;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckoutSession>
 */
class CheckoutSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'livemode' => false,
            'client_reference_id' => 'order-'.fake()->unique()->numberBetween(1000, 999999),
            'mode' => 'payment',
            'products' => [['name' => 'Karkade, 1 kg', 'quantity' => 2, 'unit_amount' => 4_750]],
            'total_amount' => 9_500,
            'currency' => 'SDG',
            'success_url' => 'https://shop.example/success',
            'cancel_url' => 'https://shop.example/cancel',
            'payment_status' => CheckoutPaymentStatus::Unpaid,
            'invoice' => (string) fake()->unique()->numberBetween(100_000, 99_999_999),
            'save_card_on_success' => false,
            'metadata' => ['Customer name' => 'Mohamed Elamin'],
            'expire_at' => now()->addDay(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expire_at' => now()->subMinute()]);
    }

    public function paid(): static
    {
        return $this->state(fn () => ['payment_status' => CheckoutPaymentStatus::Paid, 'paid_at' => now()]);
    }
}
