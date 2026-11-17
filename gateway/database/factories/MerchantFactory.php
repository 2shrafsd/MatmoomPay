<?php

namespace Database\Factories;

use App\Enums\MerchantRole;
use App\Enums\MerchantStatus;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Merchant>
 */
class MerchantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['Nile Aroma', 'Omdurman Roastery', 'Karkade House', 'Port Sudan Supplies', 'Khartoum Florist', 'Kordofan Gum Co.']);

        return [
            'name' => $name,
            'legal_name' => $name.' LLC',
            'activity' => fake()->randomElement(['Retail', 'Food and beverage', 'Online store', 'Services']),
            'commercial_registration' => (string) fake()->numberBetween(1000000, 1999999),
            'email' => fake()->unique()->companyEmail(),
            'phone' => '+249 9'.fake()->numerify('# ### ####'),
            'website' => 'https://'.fake()->domainName(),
            'country' => 'SD',
            'status' => MerchantStatus::Active,
            'fee_rate_bps' => 250,
            'fee_fixed' => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => MerchantStatus::Pending]);
    }

    /**
     * A payout account an admin already verified.
     */
    public function withSettlementAccount(): static
    {
        return $this->state(fn () => [
            'settlement_bank_name' => 'Bank of Khartoum',
            'settlement_account_name' => 'Nile Aroma LLC',
            'settlement_iban' => 'SD2129000501234001234567',
            'settlement_account_verified_at' => now(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => MerchantStatus::Suspended]);
    }

    public function banned(): static
    {
        return $this->state(fn () => ['status' => MerchantStatus::Banned, 'status_reason' => 'Fraud reports']);
    }

    public function withMember(User $user, MerchantRole $role = MerchantRole::Owner): static
    {
        return $this->afterCreating(function (Merchant $merchant) use ($user, $role) {
            $merchant->users()->attach($user->id, ['role' => $role->value]);
            $user->forceFill(['current_merchant_id' => $merchant->id])->save();
        });
    }
}
