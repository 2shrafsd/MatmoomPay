<?php

use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

beforeEach(fn () => Queue::fake());

function intentPayload(array $overrides = []): array
{
    return array_replace([
        'amount' => 5_000,
        'client_reference_id' => 'subscription-77',
        'return_url' => 'https://shop.example/return',
        'metadata' => ['plan' => 'monthly'],
    ], $overrides);
}

test('a saved card is charged off-session through a payment intent', function () {
    [$key, $headers] = apiKeyFor();
    $card = PaymentMethod::factory()->for(Customer::factory()->for($key->merchant))->create();

    $intent = $this->postJson('/api/v1/payment_intents', intentPayload(['payment_method_id' => $card->public_id]), $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'requires_confirmation')
        ->json('data');

    $this->postJson("/api/v1/payment_intents/{$intent['id']}/confirm", [], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'succeeded');

    $payment = Payment::sole();
    expect($payment->status)->toBe(PaymentStatus::Successful)->and($payment->amount)->toBe(5_000);

    $this->getJson('/api/v1/payments?payment_intent='.$intent['id'], $headers)->assertJsonPath('data.0.payment_id', $payment->public_id);
});

test('a card that needs 3-D Secure returns a next action and completes on the hosted page', function () {
    [$key, $headers] = apiKeyFor();
    $card = PaymentMethod::factory()->for(Customer::factory()->for($key->merchant))->card('4456530000001096')->create();
    $intent = PaymentIntent::factory()->for($key->merchant)->create();

    $response = $this->postJson("/api/v1/payment_intents/{$intent->public_id}/confirm", ['payment_method_id' => $card->public_id], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'requires_action')
        ->assertJsonPath('data.next_action.return_url', 'https://shop.example/return');

    $url = $response->json('data.next_action.url');
    $this->get($url)->assertOk()->assertSee('3-D Secure');

    $payment = Payment::sole();
    $submitUrl = URL::temporarySignedRoute('authenticate.submit', now()->addMinutes(5), ['payment' => $payment->public_id]);
    $this->post($submitUrl, ['code' => '1234'])->assertRedirect('https://shop.example/return');

    expect($intent->refresh()->status->value)->toBe('succeeded');
});

test('the hosted verification page rejects unsigned urls', function () {
    $payment = Payment::factory()->create();

    $this->get(route('authenticate.show', $payment))->assertForbidden();
});

test('a declined off-session charge leaves the intent waiting for a new payment method', function () {
    [$key, $headers] = apiKeyFor();
    $card = PaymentMethod::factory()->for(Customer::factory()->for($key->merchant))->card('4000000000000002')->create();
    $intent = PaymentIntent::factory()->for($key->merchant)->create();

    $this->postJson("/api/v1/payment_intents/{$intent->public_id}/confirm", ['payment_method_id' => $card->public_id], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'requires_payment_method');

    expect(Payment::sole()->status)->toBe(PaymentStatus::Failed);
});

test('confirming without a payment method is rejected', function () {
    [$key, $headers] = apiKeyFor();
    $intent = PaymentIntent::factory()->for($key->merchant)->create();

    $this->postJson("/api/v1/payment_intents/{$intent->public_id}/confirm", [], $headers)
        ->assertStatus(400)
        ->assertJsonFragment(['field' => 'payment_method_id']);
});

test('another merchant saved card cannot be charged', function () {
    [, $headers] = apiKeyFor();
    $foreignCard = PaymentMethod::factory()->create();

    $this->postJson('/api/v1/payment_intents', intentPayload(['payment_method_id' => $foreignCard->public_id]), $headers)
        ->assertStatus(400)
        ->assertJsonFragment(['field' => 'payment_method_id']);
});

test('an expired intent cannot be confirmed', function () {
    [$key, $headers] = apiKeyFor();
    $card = PaymentMethod::factory()->for(Customer::factory()->for($key->merchant))->create();
    $intent = PaymentIntent::factory()->for($key->merchant)->create(['expire_at' => now()->subMinute(), 'payment_method_id' => $card->id]);

    $this->postJson("/api/v1/payment_intents/{$intent->public_id}/confirm", [], $headers)->assertStatus(400)->assertJsonPath('code', 4006);
});

test('an intent can be cancelled once and is found by its client reference', function () {
    [$key, $headers] = apiKeyFor();
    $intent = PaymentIntent::factory()->for($key->merchant)->create();

    $this->getJson("/api/v1/payment_intents/{$intent->client_reference_id}/reference", $headers)->assertJsonPath('data.id', $intent->public_id);
    $this->postJson("/api/v1/payment_intents/{$intent->public_id}/cancel", [], $headers)->assertOk()->assertJsonPath('data.status', 'cancelled');
    $this->postJson("/api/v1/payment_intents/{$intent->public_id}/cancel", [], $headers)->assertStatus(400);
});
