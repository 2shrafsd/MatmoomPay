<?php

use App\Enums\CheckoutPaymentStatus;
use App\Jobs\DeliverWebhook;
use App\Models\CheckoutSession;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Queue;

test('a merchant creates a checkout session and the total is computed on the server', function () {
    [$key, $headers] = apiKeyFor();

    $response = $this->postJson('/api/v1/checkout/session', checkoutPayload(['total_amount' => 1]), $headers);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('code', 2001)
        ->assertJsonPath('data.total_amount', 7_500)
        ->assertJsonPath('data.currency', 'SDG')
        ->assertJsonPath('data.payment_status', 'unpaid')
        ->assertJsonPath('data.client_reference_id', 'order-1001')
        ->assertJsonPath('data.metadata.order id', 1001);

    expect($response->json('data.session_id'))->toStartWith('checkout_');

    $session = CheckoutSession::sole();
    expect($session->merchant_id)->toBe($key->merchant_id)
        ->and($session->livemode)->toBeFalse()
        ->and($session->expire_at->diffInMinutes(now()->addDay(), true))->toBeLessThan(2);
});

test('invalid sessions are rejected with field errors', function () {
    [, $headers] = apiKeyFor();

    $this->postJson('/api/v1/checkout/session', checkoutPayload([
        'products' => [['name' => str_repeat('a', 41), 'quantity' => 0, 'unit_amount' => -5]],
        'success_url' => 'javascript:alert(1)',
    ]), $headers)
        ->assertStatus(400)
        ->assertJsonPath('code', 4000)
        ->assertJsonPath('description', 'Invalid information')
        ->assertJsonFragment(['field' => 'products.0.name'])
        ->assertJsonFragment(['field' => 'products.0.quantity'])
        ->assertJsonFragment(['field' => 'products.0.unit_amount'])
        ->assertJsonFragment(['field' => 'success_url']);

    expect(CheckoutSession::count())->toBe(0);
});

test('unknown product keys are rejected', function () {
    [, $headers] = apiKeyFor();

    $this->postJson('/api/v1/checkout/session', checkoutPayload([
        'products' => [['name' => 'Dates', 'quantity' => 1, 'unit_amount' => 100, 'discount' => 100]],
    ]), $headers)->assertStatus(400)->assertJsonFragment(['field' => 'products.0']);
});

test('live sessions require https redirect urls', function () {
    [, $headers] = apiKeyFor(livemode: true);

    $this->postJson('/api/v1/checkout/session', checkoutPayload(['success_url' => 'http://shop.example/ok']), $headers)
        ->assertStatus(400)
        ->assertJsonFragment(['field' => 'success_url']);
});

test('a customer from another merchant cannot be attached', function () {
    [, $headers] = apiKeyFor();
    $foreignCustomer = Customer::factory()->create();

    $this->postJson('/api/v1/checkout/session', checkoutPayload(['customer_id' => $foreignCustomer->public_id]), $headers)
        ->assertStatus(400)
        ->assertJsonFragment(['field' => 'customer_id']);
});

test('sessions can be retrieved by id, client reference and invoice', function () {
    [$key, $headers] = apiKeyFor();
    $session = CheckoutSession::factory()->for($key->merchant)->create();

    $this->getJson("/api/v1/checkout/session/{$session->public_id}", $headers)->assertOk()->assertJsonPath('data.invoice', $session->invoice);
    $this->getJson("/api/v1/checkout/reference/{$session->client_reference_id}", $headers)->assertOk()->assertJsonPath('data.session_id', $session->public_id);
    $this->getJson("/api/v1/checkout/invoice/{$session->invoice}", $headers)->assertOk()->assertJsonPath('data.session_id', $session->public_id);
});

test('a merchant cannot read another merchant session', function () {
    [, $headers] = apiKeyFor();
    $foreign = CheckoutSession::factory()->create();

    $this->getJson("/api/v1/checkout/session/{$foreign->public_id}", $headers)
        ->assertNotFound()
        ->assertExactJson(['success' => false, 'code' => 4003, 'description' => 'object not found']);
});

test('test keys never see live sessions of the same merchant', function () {
    $merchant = Merchant::factory()->create();
    [, $headers] = apiKeyFor($merchant);
    $live = CheckoutSession::factory()->for($merchant)->create(['livemode' => true]);

    $this->getJson("/api/v1/checkout/session/{$live->public_id}", $headers)->assertNotFound();
    $this->getJson('/api/v1/checkout/session', $headers)->assertOk()->assertJsonCount(0, 'data');
});

test('sessions are listed newest first with limit and skip', function () {
    [$key, $headers] = apiKeyFor();
    $sessions = CheckoutSession::factory()->for($key->merchant)->count(3)->create();

    $this->getJson('/api/v1/checkout/session?limit=2&skip=0', $headers)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.session_id', $sessions[2]->public_id);

    $this->getJson('/api/v1/checkout/session?limit=500', $headers)->assertStatus(400);
});

test('an unpaid session can be cancelled but a paid one cannot', function () {
    [$key, $headers] = apiKeyFor();
    $unpaid = CheckoutSession::factory()->for($key->merchant)->create();
    $paid = CheckoutSession::factory()->for($key->merchant)->paid()->create();

    $this->postJson("/api/v1/checkout/{$unpaid->public_id}/cancel", [], $headers)
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'cancelled');

    $this->postJson("/api/v1/checkout/{$paid->public_id}/cancel", [], $headers)
        ->assertStatus(400)
        ->assertJsonPath('code', 4101);

    expect($paid->refresh()->payment_status)->toBe(CheckoutPaymentStatus::Paid);
});

test('creating a session queues a signed checkout.created webhook', function () {
    Queue::fake();
    [$key, $headers] = apiKeyFor();
    WebhookEndpoint::factory()->for($key->merchant)->create();

    $this->postJson('/api/v1/checkout/session', checkoutPayload(), $headers)->assertOk();

    $delivery = WebhookDelivery::sole();
    expect($delivery->event_type)->toBe('checkout.created')
        ->and(json_decode($delivery->payload, true)['data']['total_amount'])->toBe(7_500);

    Queue::assertPushed(DeliverWebhook::class);
});

test('repeating a request with the same idempotency key returns the first result', function () {
    [, $headers] = apiKeyFor();
    $headers['Idempotency-Key'] = 'order-1001-attempt';

    $first = $this->postJson('/api/v1/checkout/session', checkoutPayload(), $headers)->assertOk();
    $second = $this->postJson('/api/v1/checkout/session', checkoutPayload(), $headers)->assertOk()->assertHeader('Idempotent-Replayed', 'true');

    expect($second->json('data.session_id'))->toBe($first->json('data.session_id'))
        ->and(CheckoutSession::count())->toBe(1);

    $this->postJson('/api/v1/checkout/session', checkoutPayload(['client_reference_id' => 'other']), $headers)
        ->assertStatus(400)
        ->assertJsonPath('code', 4000);
});
