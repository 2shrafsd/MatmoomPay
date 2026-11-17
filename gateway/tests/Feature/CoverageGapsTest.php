<?php

use App\Actions\Merchants\ChangeMerchantStatus;
use App\Actions\Webhooks\ResendWebhookDelivery;
use App\Actions\Webhooks\SendWebhook;
use App\Enums\MerchantRole;
use App\Enums\MerchantStatus;
use App\Enums\WebhookDeliveryStatus;
use App\Jobs\DeliverWebhook;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\IdempotencyKey;
use App\Models\Merchant;
use App\Models\PaymentLink;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Rules\LuhnCardNumber;
use App\Support\GatewayAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(fn () => Queue::fake());

function pendingDelivery(): WebhookDelivery
{
    $endpoint = WebhookEndpoint::factory()->create(['url' => 'https://93.184.215.14/hooks']);

    return WebhookDelivery::create([
        'merchant_id' => $endpoint->merchant_id,
        'webhook_endpoint_id' => $endpoint->id,
        'livemode' => false,
        'event_type' => 'payment.succeeded',
        'payload' => '{"id":"evt_1"}',
        'status' => WebhookDeliveryStatus::Pending,
    ]);
}

test('a webhook that keeps failing is marked failed after its last attempt', function () {
    Http::fake(['*' => Http::response('down', 500)]);
    $delivery = pendingDelivery();
    $job = (new DeliverWebhook($delivery))->withFakeQueueInteractions();
    $job->job->attempts = config('gateway.webhooks.max_attempts');

    $job->handle(app(SendWebhook::class));

    expect($delivery->refresh()->status)->toBe(WebhookDeliveryStatus::Failed);
});

test('a failed webhook can be resent from the dashboard', function () {
    $delivery = pendingDelivery();
    $delivery->update(['status' => WebhookDeliveryStatus::Failed]);

    app(ResendWebhookDelivery::class)->handle($delivery);

    expect($delivery->refresh()->status)->toBe(WebhookDeliveryStatus::Pending);
    Queue::assertPushed(DeliverWebhook::class);
});

test('reusing an idempotency key with a different body is refused', function () {
    [, $headers] = apiKeyFor();
    $headers['Idempotency-Key'] = 'same-key';

    $this->postJson('/api/v1/checkout/session', checkoutPayload(), $headers)->assertOk();
    $this->postJson('/api/v1/checkout/session', checkoutPayload(['client_reference_id' => 'other']), $headers)
        ->assertStatus(400)
        ->assertJsonPath('code', 4000);
});

test('expired idempotency keys are pruned', function () {
    [$key] = apiKeyFor();
    IdempotencyKey::create(['merchant_id' => $key->merchant_id, 'livemode' => false, 'key' => 'old', 'request_fingerprint' => 'x', 'expires_at' => now()->subHour()]);

    $this->artisan('gateway:prune-idempotency-keys')->assertSuccessful();

    expect(IdempotencyKey::count())->toBe(0);
});

test('a key over its rate limit gets HTTP 429', function () {
    config(['gateway.api.rate_limit_per_minute' => 1]);
    [, $headers] = apiKeyFor();

    $this->getJson('/api/v1/customers', $headers)->assertOk();
    $this->getJson('/api/v1/customers', $headers)->assertStatus(429)->assertJsonPath('code', 4290);
});

test('a developer creates and revokes an API key from the dashboard and both are audited', function () {
    $owner = User::factory()->create();
    $merchant = Merchant::factory()->withMember($owner, MerchantRole::Owner)->create();
    $this->actingAs($owner)->withSession(['auth.password_confirmed_at' => time()]);
    app()->instance(GatewayAccount::class, new GatewayAccount($merchant, false));

    Livewire::test('pages::portal.developers.keys')
        ->set('name', 'Server')
        ->set('type', 'secret')
        ->call('create')
        ->assertSet('revealedToken', fn (?string $token) => str_starts_with((string) $token, 'sk_test_'));

    $key = ApiKey::query()->where('name', 'Server')->sole();

    Livewire::test('pages::portal.developers.keys')->call('revoke', $key->public_id);

    expect($key->refresh()->isRevoked())->toBeTrue()
        ->and(AuditLog::whereIn('action', ['api_key.created', 'api_key.revoked'])->count())->toBe(2);
});

test('a payment link enforces its amount range and stops when closed', function () {
    $link = PaymentLink::factory()->create(['amount' => null, 'min_amount' => 1_000, 'max_amount' => 5_000]);

    $this->post(route('links.start', $link), ['name' => 'Amna', 'amount' => '500'])->assertSessionHasErrors('amount');
    $this->post(route('links.start', $link), ['name' => 'Amna', 'amount' => '9,000'])->assertSessionHasErrors('amount');
    $this->post(route('links.start', $link), ['name' => 'Amna', 'amount' => '2,500'])->assertRedirect();

    $link->update(['is_active' => false]);

    $this->get(route('links.show', $link))->assertSee('no longer accepting payments');
    $this->post(route('links.start', $link), ['name' => 'Amna', 'amount' => '2,500'])->assertSessionHasErrors();
});

test('rejecting a merchant needs a reason and reactivating restores live payments', function () {
    $merchant = Merchant::factory()->suspended()->create(['approved_at' => now()->subMonth()]);
    $admin = User::factory()->withTwoFactor()->create();
    $admin->forceFill(['is_platform_admin' => true])->save();

    $this->actingAs($admin);
    Livewire::test('pages::admin.merchants.show', ['merchant' => Merchant::factory()->pending()->create()])
        ->call('changeStatus', 'rejected')
        ->assertHasErrors('reason');

    app(ChangeMerchantStatus::class)->handle($merchant, MerchantStatus::Active, $admin);

    expect($merchant->refresh()->status)->toBe(MerchantStatus::Active);
});

test('only platform admins may change a merchant status', function () {
    expect(fn () => app(ChangeMerchantStatus::class)->handle(Merchant::factory()->pending()->create(), MerchantStatus::Active, User::factory()->create()))
        ->toThrow(InvalidArgumentException::class);
});

test('card numbers must pass the Luhn checksum', function (string $number, bool $valid) {
    $passed = true;
    (new LuhnCardNumber)->validate('card_number', $number, function () use (&$passed) {
        $passed = false;
    });

    expect($passed)->toBe($valid);
})->with([
    ['4242424242424242', true],
    ['5123450000000008', true],
    ['4242424242424241', false],
    ['1234', false],
]);
