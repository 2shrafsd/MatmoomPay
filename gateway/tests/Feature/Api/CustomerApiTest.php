<?php

use App\Models\Customer;
use App\Models\PaymentMethod;

test('customers are created once per client id', function () {
    [, $headers] = apiKeyFor();

    $this->postJson('/api/v1/customers', ['client_customer_id' => 'aisha@example.om'], $headers)
        ->assertOk()
        ->assertJsonPath('data.customer_client_id', 'aisha@example.om');

    $this->postJson('/api/v1/customers', ['client_customer_id' => 'aisha@example.om'], $headers)
        ->assertStatus(400)
        ->assertJsonPath('code', 4004);
});

test('saved cards are listed per customer without exposing tokens', function () {
    [$key, $headers] = apiKeyFor();
    $customer = Customer::factory()->for($key->merchant)->create();
    PaymentMethod::factory()->for($customer)->create();

    $response = $this->getJson("/api/v1/payment_methods?customer_id={$customer->public_id}", $headers)
        ->assertOk()
        ->assertJsonPath('data.0.masked_card', 'XXXX XXXX XXXX 4242')
        ->assertJsonPath('data.0.brand', 'Visa');

    expect($response->getContent())->not->toContain('tok_sbx_');

    $this->getJson('/api/v1/payment_methods', $headers)->assertStatus(400);
});

test('deleting a customer removes their saved cards', function () {
    [$key, $headers] = apiKeyFor();
    $customer = Customer::factory()->for($key->merchant)->create();
    PaymentMethod::factory()->for($customer)->create();

    $this->deleteJson("/api/v1/customers/{$customer->public_id}", [], $headers)->assertOk()->assertJsonPath('code', 2003);

    expect(Customer::count())->toBe(0)->and(PaymentMethod::count())->toBe(0);
});

test('a merchant cannot delete another merchant customer or card', function () {
    [, $headers] = apiKeyFor();
    $card = PaymentMethod::factory()->create();

    $this->deleteJson("/api/v1/customers/{$card->customer->public_id}", [], $headers)->assertNotFound();
    $this->deleteJson("/api/v1/payment_methods/{$card->public_id}", [], $headers)->assertNotFound();

    expect(PaymentMethod::count())->toBe(1);
});
