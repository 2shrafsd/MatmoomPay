<?php

namespace Database\Factories;

use App\Enums\ApiKeyType;
use App\Models\ApiKey;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    /**
     * The plain token of the last key made by this factory, so tests can authenticate with it.
     */
    public static ?string $lastToken = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'livemode' => false,
            'type' => ApiKeyType::Secret,
            'name' => 'Test key',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ApiKey $key) {
            $token = $key->type->prefix($key->livemode).Str::random(40);
            static::$lastToken = $token;

            $key->token_prefix = $key->type->prefix($key->livemode);
            $key->token_hash = ApiKey::hashToken($token);
            $key->last_four = substr($token, -4);
            $key->publishable_token = $key->type === ApiKeyType::Publishable ? $token : null;
        });
    }

    public function live(): static
    {
        return $this->state(fn () => ['livemode' => true]);
    }

    public function publishable(): static
    {
        return $this->state(fn () => ['type' => ApiKeyType::Publishable]);
    }
}
