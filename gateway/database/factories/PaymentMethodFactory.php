<?php

namespace Database\Factories;

use App\Enums\CardBrand;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Services\Payments\Data\CardDetails;
use App\Services\Payments\Processors\SandboxProcessor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'merchant_id' => fn (array $attributes) => Customer::query()->whereKey($attributes['customer_id'])->value('merchant_id'),
            'livemode' => fn (array $attributes) => (bool) Customer::query()->whereKey($attributes['customer_id'])->value('livemode'),
            'processor' => 'sandbox',
            'nickname' => null,
            'brand' => CardBrand::Visa,
            'expiry_month' => 12,
            'expiry_year' => (int) now()->addYears(3)->format('Y'),
            ...$this->tokenFor('4242424242424242'),
        ];
    }

    /**
     * A saved card backed by a real sandbox token for the given test card number.
     */
    public function card(string $number): static
    {
        return $this->state(fn () => $this->tokenFor($number));
    }

    /**
     * @return array<string, mixed>
     */
    private function tokenFor(string $number): array
    {
        $token = app(SandboxProcessor::class)->tokenize(new CardDetails($number, 12, (int) now()->addYears(3)->format('Y'), '123', 'Test'));

        return [
            'processor_token' => $token->token,
            'fingerprint' => $token->fingerprint,
            'bin' => $token->bin,
            'last_four' => $token->lastFour,
            'brand' => $token->brand,
            'card_type' => $token->type,
        ];
    }
}
