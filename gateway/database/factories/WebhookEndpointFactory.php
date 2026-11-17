<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'livemode' => false,
            'url' => 'https://merchant.example/webhooks/matmoom',
            'secret' => 'whsec_'.fake()->regexify('[A-Za-z0-9]{40}'),
            'is_active' => true,
        ];
    }
}
