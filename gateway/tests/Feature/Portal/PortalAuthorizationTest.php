<?php

use App\Actions\Team\InviteTeamMember;
use App\Enums\ApiKeyType;
use App\Enums\MerchantRole;
use App\Enums\MerchantStatus;
use App\Exceptions\GatewayException;
use App\Http\Middleware\EnsureMerchantAccount;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Policies\MerchantPolicy;
use App\Support\GatewayAccount;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function memberOf(Merchant $merchant, MerchantRole $role): User
{
    $user = User::factory()->create();
    $merchant->users()->attach($user->id, ['role' => $role->value]);
    $user->forceFill(['current_merchant_id' => $merchant->id])->save();

    return $user;
}

beforeEach(function () {
    $this->merchant = Merchant::factory()->create();
});

test('the role matrix grants each ability to the right roles', function (string $ability, array $allowed) {
    $policy = new MerchantPolicy;

    foreach (MerchantRole::cases() as $role) {
        $user = memberOf($this->merchant, $role);

        expect($policy->{$ability}($user, $this->merchant))->toBe(in_array($role, $allowed, true), "{$role->value} → {$ability}");
    }
})->with([
    'refund' => ['refund', [MerchantRole::Owner, MerchantRole::Admin, MerchantRole::Finance]],
    'manageTeam' => ['manageTeam', [MerchantRole::Owner, MerchantRole::Admin]],
    'manageDevelopers' => ['manageDevelopers', [MerchantRole::Owner, MerchantRole::Admin, MerchantRole::Developer]],
    'managePaymentLinks' => ['managePaymentLinks', [MerchantRole::Owner, MerchantRole::Admin, MerchantRole::Finance]],
    'update' => ['update', [MerchantRole::Owner, MerchantRole::Admin]],
]);

test('a merchant cannot open another merchant payment', function () {
    $owner = memberOf($this->merchant, MerchantRole::Owner);
    $foreign = Payment::factory()->create();

    $this->actingAs($owner)->get(route('portal.payments.show', $foreign))->assertNotFound();
});

test('test-mode pages never show live records', function () {
    $owner = memberOf($this->merchant, MerchantRole::Owner);
    $live = Payment::factory()->for($this->merchant)->create(['livemode' => true]);

    $this->actingAs($owner)->get(route('portal.payments.show', $live))->assertNotFound();
});

test('a viewer cannot refund through the livewire action', function () {
    $viewer = memberOf($this->merchant, MerchantRole::Viewer);
    $payment = Payment::factory()->for($this->merchant)->create();

    $this->actingAs($viewer);
    app()->instance(GatewayAccount::class, new GatewayAccount($this->merchant, false));

    Livewire::test('pages::portal.payments.show', ['payment' => $payment])
        ->set('refundAmount', '1.000')
        ->set('refundReason', 'Trying anyway')
        ->call('refund')
        ->assertForbidden();

    expect($payment->refresh()->refunded_amount)->toBe(0);
});

test('finance can refund from the dashboard and the action is audited', function () {
    $finance = memberOf($this->merchant, MerchantRole::Finance);
    $payment = Payment::factory()->for($this->merchant)->create(['amount' => 9_500]);

    $this->actingAs($finance);
    app()->instance(GatewayAccount::class, new GatewayAccount($this->merchant, false));

    Livewire::test('pages::portal.payments.show', ['payment' => $payment])
        ->set('refundAmount', '2,500')
        ->set('refundReason', 'Late delivery')
        ->call('refund')
        ->assertHasNoErrors();

    expect($payment->refresh()->refunded_amount)->toBe(2_500)
        ->and(AuditLog::where('action', 'refund.created')->where('actor_id', $finance->id)->exists())->toBeTrue();
});

test('a developer creates a secret key that is shown once and stored hashed', function () {
    $developer = memberOf($this->merchant, MerchantRole::Developer);

    $this->actingAs($developer);
    app()->instance(GatewayAccount::class, new GatewayAccount($this->merchant, false));

    $component = Livewire::test('pages::portal.developers.keys')
        ->set('name', 'Store server')
        ->set('type', 'secret')
        ->call('create');

    $token = $component->get('revealedToken');
    $key = ApiKey::where('type', ApiKeyType::Secret)->sole();

    expect($token)->toStartWith('sk_test_')
        ->and($key->token_hash)->toBe(ApiKey::hashToken($token))
        ->and(json_encode($key->getAttributes()))->not->toContain($token);
});

test('a viewer cannot create api keys', function () {
    $viewer = memberOf($this->merchant, MerchantRole::Viewer);

    $this->actingAs($viewer);
    app()->instance(GatewayAccount::class, new GatewayAccount($this->merchant, false));

    Livewire::test('pages::portal.developers.keys')
        ->set('name', 'Sneaky')
        ->call('create')
        ->assertForbidden();

    expect(ApiKey::count())->toBe(0);
});

test('live mode stays off until the merchant is approved', function () {
    $merchant = Merchant::factory()->pending()->create();
    $owner = memberOf($merchant, MerchantRole::Owner);

    $this->actingAs($owner)->post(route('portal.mode'), ['live' => '1']);

    expect(session(EnsureMerchantAccount::MODE_SESSION_KEY, false))->toBeFalse();
});

test('the webhook url cannot point at a private address in live mode', function () {
    $owner = memberOf($this->merchant, MerchantRole::Owner);

    $this->actingAs($owner);
    app()->instance(GatewayAccount::class, new GatewayAccount($this->merchant, true));

    Livewire::test('pages::portal.developers.webhooks')
        ->set('url', 'http://example.com/hooks')
        ->call('save')
        ->assertHasErrors('url');
});

test('payment links from another merchant cannot be toggled', function () {
    $owner = memberOf($this->merchant, MerchantRole::Owner);
    $foreign = PaymentLink::factory()->create();

    $this->actingAs($owner)->get(route('portal.links.show', $foreign))->assertNotFound();
});

test('the owner cannot be removed from the team', function () {
    $owner = memberOf($this->merchant, MerchantRole::Owner);
    $admin = memberOf($this->merchant, MerchantRole::Admin);

    $this->actingAs($admin);
    app()->instance(GatewayAccount::class, new GatewayAccount($this->merchant, false));

    Livewire::test('pages::portal.team')->call('remove', $owner->id);

    expect($owner->roleIn($this->merchant))->toBe(MerchantRole::Owner);
});

test('switching to a merchant you do not belong to is refused', function () {
    $owner = memberOf($this->merchant, MerchantRole::Owner);
    $other = Merchant::factory()->create();

    $this->actingAs($owner)->post(route('portal.merchant', $other))->assertNotFound();

    expect($owner->refresh()->current_merchant_id)->toBe($this->merchant->id);
});

test('onboarding opens a pending merchant with a test publishable key', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::portal.onboarding')
        ->set('name', 'Sur Marine Supplies')
        ->set('legal_name', 'Sur Marine Supplies LLC')
        ->set('activity', 'retail')
        ->set('email', 'hello@surmarine.test')
        ->set('phone', '+249 91 555 1234')
        ->call('save')
        ->assertRedirect(route('dashboard'));

    $merchant = $user->refresh()->currentMerchant;

    expect($merchant->status)->toBe(MerchantStatus::Pending)
        ->and($user->roleIn($merchant))->toBe(MerchantRole::Owner)
        ->and($merchant->apiKeys()->where('type', ApiKeyType::Publishable)->where('livemode', false)->count())->toBe(1);
});

test('a viewer cannot reveal the webhook signing secret by tampering with component state', function () {
    $viewer = memberOf($this->merchant, MerchantRole::Viewer);
    $endpoint = WebhookEndpoint::factory()->for($this->merchant)->create();

    $this->actingAs($viewer);
    app()->instance(GatewayAccount::class, new GatewayAccount($this->merchant, false));

    Livewire::test('pages::portal.developers.webhooks')
        ->set('showSecret', true)
        ->assertDontSee($endpoint->secret);
});

test('team invitations are rate limited per merchant', function () {
    Notification::fake();
    $owner = memberOf($this->merchant, MerchantRole::Owner);
    $invite = app(InviteTeamMember::class);

    foreach (range(1, 10) as $i) {
        $invite->handle($this->merchant, "person{$i}@example.om", MerchantRole::Viewer, $owner);
    }

    expect(fn () => $invite->handle($this->merchant, 'one-more@example.om', MerchantRole::Viewer, $owner))
        ->toThrow(GatewayException::class);
});
