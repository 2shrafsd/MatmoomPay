<?php

use App\Actions\Payments\FinalizeSuccessfulPayment;
use App\Actions\Refunds\CreateRefund;
use App\Actions\Settlements\CreateSettlement;
use App\Actions\Webhooks\SendWebhook;
use App\Enums\MerchantRole;
use App\Enums\TeamNotification;
use App\Enums\WebhookDeliveryStatus;
use App\Jobs\DeliverWebhook;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\Settlement;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Notifications\PaymentReceived;
use App\Notifications\RefundProcessed;
use App\Notifications\SettlementUpdated;
use App\Notifications\WebhookEndpointFailing;
use App\Services\Payments\Processors\SandboxProcessor;
use App\Services\Payments\Processors\UnconfiguredProcessor;
use App\Support\GatewayAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    Notification::fake();
});

function teamMember(Merchant $merchant, MerchantRole $role): User
{
    $user = User::factory()->create();
    $merchant->users()->attach($user->id, ['role' => $role->value]);

    return $user;
}

function livePayment(Merchant $merchant, int $amount = 10_000): Payment
{
    return app(FinalizeSuccessfulPayment::class)->handle(
        Payment::factory()->for($merchant)->create(['livemode' => true, 'amount' => $amount, 'status' => 'InProcess', 'card_token' => null]),
    );
}

test('the balance page splits what can be settled from what is still on hold', function () {
    $owner = User::factory()->create();
    $merchant = Merchant::factory()->withMember($owner)->create(['fee_rate_bps' => 0]);
    Payment::factory()->for($merchant)->create(['amount' => 4_000, 'status' => 'InProcess', 'card_token' => null]);
    app(FinalizeSuccessfulPayment::class)->handle(Payment::sole());
    $this->travel(3)->days();
    app(FinalizeSuccessfulPayment::class)->handle(Payment::factory()->for($merchant)->create(['amount' => 1_000, 'status' => 'InProcess', 'card_token' => null]));

    $this->actingAs($owner);
    app()->instance(GatewayAccount::class, new GatewayAccount($merchant, false));

    Livewire::test('pages::portal.balance')
        ->assertSet('type', '')
        ->assertSeeText('4,000')
        ->assertSeeText('1,000')
        ->tap(fn ($component) => expect($component->instance()->totals)->toBe(['available' => 4_000, 'on_hold' => 1_000]));
});

test('a merchant opens and exports only their own settlements', function () {
    $owner = User::factory()->create();
    $merchant = Merchant::factory()->withSettlementAccount()->withMember($owner)->create(['fee_rate_bps' => 0]);
    livePayment($merchant, 7_000);
    $this->travel(3)->days();
    $settlement = app(CreateSettlement::class)->handle($merchant, User::factory()->withTwoFactor()->create()->forceFill(['is_platform_admin' => true]));
    $foreign = Settlement::create(['merchant_id' => Merchant::factory()->create()->id, 'currency' => 'SDG', 'gross_amount' => 1, 'fee_amount' => 0, 'refund_amount' => 0, 'net_amount' => 1, 'transactions_count' => 1, 'status' => 'pending']);

    $this->actingAs($owner);

    $this->get(route('portal.settlements.show', $settlement->public_id))->assertOk()->assertSee('7,000');
    $this->get(route('portal.settlements.show', $foreign->public_id))->assertNotFound();

    $csv = $this->get(route('portal.settlements.export', $settlement->public_id))->assertOk()->streamedContent();
    expect($csv)->toContain($settlement->public_id.',payment,7000,SDG');
    $this->get(route('portal.settlements.export', $foreign->public_id))->assertNotFound();
});

test('a payment link offers its QR code as a PNG for chat apps', function () {
    $owner = User::factory()->create();
    $merchant = Merchant::factory()->withMember($owner)->create();
    $link = PaymentLink::factory()->for($merchant)->create();
    $this->actingAs($owner);
    app()->instance(GatewayAccount::class, new GatewayAccount($merchant, false));

    Livewire::test('pages::portal.links.show', ['link' => $link])->assertSeeHtml('data:image/png;base64,');
});

test('a developer can send a test webhook', function () {
    $owner = User::factory()->create();
    $merchant = Merchant::factory()->withMember($owner)->create();
    WebhookEndpoint::factory()->for($merchant)->create();
    $this->actingAs($owner)->withSession(['auth.password_confirmed_at' => time()]);
    app()->instance(GatewayAccount::class, new GatewayAccount($merchant, false));

    Livewire::test('pages::portal.developers.webhooks')->call('sendTest');

    expect(WebhookDelivery::sole()->event_type)->toBe('webhook.test');
    Queue::assertPushed(DeliverWebhook::class);
});

test('every API response carries a request id', function () {
    [, $headers] = apiKeyFor();

    $this->getJson('/api/v1/customers', $headers)->assertHeader('X-Request-Id');
    $this->getJson('/api/v1/customers', ['matmoom-api-key' => 'wrong'])->assertHeader('X-Request-Id');
});

test('the OpenAPI description covers every API endpoint', function () {
    $spec = json_decode(file_get_contents(public_path('openapi.json')), true, flags: JSON_THROW_ON_ERROR);

    $missing = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/'))
        ->flatMap(fn ($route) => collect($route->methods())->reject(fn ($method) => $method === 'HEAD')->map(fn ($method) => [strtolower($method), '/'.substr($route->uri(), strlen('api/v1/'))]))
        ->reject(fn (array $operation) => isset($spec['paths'][preg_replace('/\{(\w+)\}/', '{$1}', $operation[1])][$operation[0]]))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

test('team emails follow roles and personal preferences', function () {
    $merchant = Merchant::factory()->withSettlementAccount()->create(['fee_rate_bps' => 0]);
    $owner = teamMember($merchant, MerchantRole::Owner);
    $finance = teamMember($merchant, MerchantRole::Finance);
    $developer = teamMember($merchant, MerchantRole::Developer);
    $owner->forceFill(['notification_preferences' => [TeamNotification::PaymentReceived->value => true]])->save();

    // Stand in for an acquirer so a live refund can go through in the test.
    app()->instance(UnconfiguredProcessor::class, app(SandboxProcessor::class));

    $payment = livePayment($merchant);
    Notification::assertSentTo($owner, PaymentReceived::class);
    Notification::assertNotSentTo([$finance, $developer], PaymentReceived::class);

    app(CreateRefund::class)->handle($payment, 2_000, 'Damaged');
    Notification::assertSentTo([$owner, $finance], RefundProcessed::class);
    Notification::assertNotSentTo($developer, RefundProcessed::class);

    $this->travel(3)->days();
    $admin = User::factory()->withTwoFactor()->create();
    $admin->forceFill(['is_platform_admin' => true])->save();
    $settlement = app(CreateSettlement::class)->handle($merchant, $admin);
    app(CreateSettlement::class)->markPaid($settlement, 'BOK-1', $admin);
    Notification::assertSentTo([$owner, $finance], SettlementUpdated::class);
});

test('a failing webhook endpoint emails developers once a day', function () {
    Http::fake(['*' => Http::response('down', 500)]);
    $endpoint = WebhookEndpoint::factory()->create(['url' => 'https://93.184.215.14/hooks']);
    $developer = teamMember($endpoint->merchant, MerchantRole::Developer);

    foreach ([1, 2] as $attempt) {
        $delivery = WebhookDelivery::create([
            'merchant_id' => $endpoint->merchant_id, 'webhook_endpoint_id' => $endpoint->id, 'livemode' => false,
            'event_type' => 'payment.succeeded', 'payload' => '{}', 'status' => WebhookDeliveryStatus::Pending,
        ]);
        $job = (new DeliverWebhook($delivery))->withFakeQueueInteractions();
        $job->job->attempts = config('gateway.webhooks.max_attempts');
        $job->handle(app(SendWebhook::class));
    }

    Notification::assertSentToTimes($developer, WebhookEndpointFailing::class, 1);
});

test('a team member chooses which emails they receive', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->assertSet('notifications.payment_received', false)
        ->set('notifications.payment_received', true)
        ->set('notifications.webhook_failing', false)
        ->call('saveNotificationPreferences');

    expect($user->refresh()->wantsNotification(TeamNotification::PaymentReceived))->toBeTrue()
        ->and($user->wantsNotification(TeamNotification::WebhookFailing))->toBeFalse();
});
