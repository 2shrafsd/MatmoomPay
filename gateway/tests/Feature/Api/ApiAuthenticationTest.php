<?php

use App\Models\ApiKey;
use App\Models\Merchant;
use Database\Factories\ApiKeyFactory;

test('requests without an api key are rejected', function () {
    $this->getJson('/api/v1/checkout/session')
        ->assertUnauthorized()
        ->assertExactJson(['success' => false, 'code' => 4010, 'description' => 'No valid API key provided.']);
});

test('a publishable key cannot call the api', function () {
    ApiKey::factory()->publishable()->create();

    $this->getJson('/api/v1/checkout/session', ['matmoom-api-key' => ApiKeyFactory::$lastToken])
        ->assertUnauthorized();
});

test('a revoked key is rejected', function () {
    [$key, $headers] = apiKeyFor();
    $key->update(['revoked_at' => now()]);

    $this->getJson('/api/v1/checkout/session', $headers)->assertUnauthorized();
});

test('live keys only work for approved merchants', function () {
    [, $headers] = apiKeyFor(Merchant::factory()->pending()->create(), livemode: true);

    $this->getJson('/api/v1/checkout/session', $headers)
        ->assertForbidden()
        ->assertJsonPath('code', 4030);
});

test('pending merchants can integrate with test keys', function () {
    [, $headers] = apiKeyFor(Merchant::factory()->pending()->create());

    $this->getJson('/api/v1/checkout/session', $headers)->assertOk();
});

test('suspended merchants lose test access too', function () {
    [, $headers] = apiKeyFor(Merchant::factory()->suspended()->create());

    $this->getJson('/api/v1/checkout/session', $headers)->assertForbidden();
});

test('the key can also be sent as a bearer token', function () {
    [, $headers] = apiKeyFor();

    $this->getJson('/api/v1/checkout/session', ['Authorization' => 'Bearer '.$headers['matmoom-api-key']])->assertOk();
});
