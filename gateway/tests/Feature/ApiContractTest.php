<?php

use App\Actions\Settlements\CreateSettlement;
use App\Actions\Webhooks\SendWebhook;
use App\Enums\CheckoutPaymentStatus;
use App\Enums\PaymentIntentStatus;
use App\Enums\PlanInterval;
use App\Enums\SubscriptionStatus;
use App\Jobs\DeliverWebhook;
use App\Models\CheckoutSession;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Settlement;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());

function subscribedCustomer(SubscriptionStatus $status): Customer
{
    $customer = Customer::factory()->create();
    Subscription::factory()->for(Plan::factory()->for($customer->merchant))->create([
        'merchant_id' => $customer->merchant_id,
        'customer_id' => $customer->id,
        'status' => $status,
    ]);

    return $customer;
}

test('a customer with an active subscription cannot be deleted', function () {
    $customer = subscribedCustomer(SubscriptionStatus::Active);
    [, $headers] = apiKeyFor($customer->merchant);

    $this->deleteJson("/api/v1/customers/{$customer->public_id}", [], $headers)->assertStatus(400)->assertJsonPath('code', 4101);

    expect($customer->fresh())->not->toBeNull();
});

test('deleting a customer keeps their past subscriptions, invoices and payments', function () {
    $customer = subscribedCustomer(SubscriptionStatus::Cancelled);
    PaymentMethod::factory()->for($customer)->create();
    $payment = Payment::factory()->for($customer->merchant)->create(['customer_id' => $customer->id]);
    [, $headers] = apiKeyFor($customer->merchant);

    $this->deleteJson("/api/v1/customers/{$customer->public_id}", [], $headers)->assertOk();

    expect(Subscription::sole()->customer_id)->toBeNull()
        ->and(PaymentMethod::count())->toBe(0)
        ->and($payment->refresh()->customer_id)->toBeNull();
});

test('every webhook carries a stable event id, its time and mode', function () {
    Http::fake(['*' => Http::response('ok')]);
    $endpoint = WebhookEndpoint::factory()->create(['url' => 'https://93.184.215.14/hooks']);
    $intent = PaymentIntent::factory()->for($endpoint->merchant)->create();

    $this->postJson("/api/v1/payment_intents/{$intent->public_id}/cancel", [], apiKeyFor($endpoint->merchant)[1])->assertOk();

    $delivery = WebhookDelivery::sole();
    $payload = json_decode($delivery->payload, true);
    expect($payload)->toHaveKeys(['id', 'event_type', 'created_at', 'livemode', 'data'])
        ->and($payload['id'])->toStartWith('evt_')
        ->and($payload['event_type'])->toBe('payment_intent.cancelled');

    app(SendWebhook::class)->handle($delivery);

    Http::assertSent(fn (Request $request) => $request->header('matmoom-event-id')[0] === $payload['id']);
});

test('expired sessions and intents are closed and announced', function () {
    $endpoint = WebhookEndpoint::factory()->create();
    $session = CheckoutSession::factory()->for($endpoint->merchant)->expired()->create();
    $intent = PaymentIntent::factory()->for($endpoint->merchant)->create(['expire_at' => now()->subMinute()]);

    $this->artisan('gateway:reconcile')->assertSuccessful();

    expect($session->refresh()->payment_status)->toBe(CheckoutPaymentStatus::Expired)
        ->and($intent->refresh()->status)->toBe(PaymentIntentStatus::Cancelled)
        ->and(WebhookDelivery::pluck('event_type')->sort()->values()->all())->toBe(['checkout.expired', 'payment_intent.cancelled']);
});

test('settlement transfers are announced to the merchant', function () {
    $endpoint = WebhookEndpoint::factory()->create(['livemode' => true]);
    $admin = User::factory()->withTwoFactor()->create();
    $settlement = Settlement::create([
        'merchant_id' => $endpoint->merchant_id,
        'currency' => 'SDG',
        'gross_amount' => 10_000,
        'fee_amount' => 250,
        'refund_amount' => 0,
        'net_amount' => 9_750,
        'transactions_count' => 2,
        'status' => 'pending',
    ]);

    app(CreateSettlement::class)->markPaid($settlement, 'BOK-778', $admin);

    $payload = json_decode(WebhookDelivery::sole()->payload, true);
    expect($payload['event_type'])->toBe('settlement.paid')
        ->and($payload['data']['net_amount'])->toBe(9_750)
        ->and($payload['data']['bank_reference'])->toBe('BOK-778');
});

test('an unsupported HTTP method gets its own error code', function () {
    [, $headers] = apiKeyFor();

    $this->putJson('/api/v1/customers', [], $headers)->assertStatus(405)->assertJsonPath('code', 4050);
});

test('a webhook whose queued job was lost is sent again', function () {
    $endpoint = WebhookEndpoint::factory()->create();
    $delivery = WebhookDelivery::create([
        'merchant_id' => $endpoint->merchant_id,
        'webhook_endpoint_id' => $endpoint->id,
        'livemode' => false,
        'event_type' => 'payment.succeeded',
        'payload' => '{}',
        'status' => 'pending',
    ]);
    WebhookDelivery::query()->whereKey($delivery->id)->update(['updated_at' => now()->subHour()]);

    $this->artisan('gateway:reconcile')->assertSuccessful();

    Queue::assertPushed(DeliverWebhook::class, fn ($job) => $job->delivery->is($delivery));
});

test('old webhook deliveries are pruned but pending ones are kept', function () {
    $endpoint = WebhookEndpoint::factory()->create();
    $make = fn (string $status) => WebhookDelivery::create([
        'merchant_id' => $endpoint->merchant_id, 'webhook_endpoint_id' => $endpoint->id, 'livemode' => false,
        'event_type' => 'payment.succeeded', 'payload' => '{}', 'status' => $status,
    ]);
    $old = $make('delivered');
    $pending = $make('pending');
    WebhookDelivery::query()->update(['created_at' => now()->subDays(120)]);

    $this->artisan('model:prune', ['--model' => [WebhookDelivery::class]])->assertSuccessful();

    expect(WebhookDelivery::pluck('id')->all())->toBe([$pending->id]);
});

test('a monthly subscription that started on the 31st returns to the 31st after February', function () {
    $interval = PlanInterval::Month;
    $february = $interval->advance(CarbonImmutable::parse('2027-01-31'), 1, 31);
    $march = $interval->advance($february, 1, 31);

    expect($february->toDateString())->toBe('2027-02-28')
        ->and($march->toDateString())->toBe('2027-03-31');
});
