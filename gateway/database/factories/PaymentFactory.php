<?php

namespace Database\Factories;

use App\Enums\CardBrand;
use App\Enums\CardType;
use App\Enums\PaymentStatus;
use App\Models\Merchant;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => now()->format('ymdHis').fake()->unique()->numerify('######'),
            'merchant_id' => Merchant::factory(),
            'livemode' => false,
            'amount' => 9_500,
            'currency' => 'SDG',
            'status' => PaymentStatus::Successful,
            'fee' => 238,
            'masked_card' => '4242 42XX XXXX 4242',
            'card_bin' => '424242',
            'card_last_four' => '4242',
            'card_brand' => CardBrand::Visa,
            'card_type' => CardType::Debit,
            'processor' => 'sandbox',
            'processor_reference' => 'sbx_'.fake()->regexify('[A-Za-z0-9]{20}'),
            'completed_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Failed, 'fee' => 0, 'failure_reason' => 'Declined by the issuing bank.']);
    }
}
