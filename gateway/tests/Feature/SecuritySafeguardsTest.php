<?php

use App\Actions\Payments\FailPayment;
use App\Actions\Team\AcceptTeamInvitation;
use App\Actions\Team\InviteTeamMember;
use App\Actions\Team\UpdateTeamMember;
use App\Enums\MerchantRole;
use App\Enums\PaymentStatus;
use App\Models\ApiKey;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Notifications\TeamInvitation;
use App\Support\GatewayAccount;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;

/**
 * Returns the decoded wire:snapshot of one component rendered in the page.
 *
 * @return array<string, mixed>
 */
function snapshotOf(string $html, string $component): array
{
    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

    foreach ($matches[1] as $encoded) {
        $snapshot = json_decode(htmlspecialchars_decode($encoded, ENT_QUOTES), true);

        if (($snapshot['memo']['name'] ?? null) === $component) {
            return $snapshot;
        }
    }

    throw new RuntimeException("Component {$component} is not on the page.");
}

/**
 * Invites an address on behalf of the given member and returns the plain token from the email.
 */
function inviteAs(Merchant $merchant, User $inviter, string $email, MerchantRole $role): string
{
    $token = null;
    app(InviteTeamMember::class)->handle($merchant, $email, $role, $inviter);

    Notification::assertSentOnDemand(TeamInvitation::class, function (TeamInvitation $notification, array $channels, object $notifiable) use (&$token, $email): bool {
        if ($notifiable->routes['mail'] !== $email) {
            return false;
        }

        $token = $notification->token;

        return true;
    });

    return (string) $token;
}

afterEach(fn () => Request::setTrustedHosts([]));

test('a Livewire update cannot skip password confirmation by asking for JSON', function () {
    $owner = User::factory()->create();
    Merchant::factory()->withMember($owner)->create();
    $this->actingAs($owner)->withSession(['auth.password_confirmed_at' => time()]);
    $snapshot = snapshotOf($this->get(route('portal.developers'))->assertOk()->getContent(), 'pages::portal.developers.keys');

    $this->withSession(['auth.password_confirmed_at' => time() - 20_000])
        ->withHeaders(['X-Livewire' => '1', 'Accept' => 'application/json'])
        ->postJson(app('livewire')->getUpdateUri(), ['components' => [[
            'snapshot' => json_encode($snapshot),
            'updates' => ['name' => 'Stolen', 'type' => 'secret'],
            'calls' => [['method' => 'create', 'params' => [], 'metadata' => []]],
        ]]])
        ->assertStatus(423);

    expect(ApiKey::query()->where('name', 'Stolen')->exists())->toBeFalse();
});

test('the team page asks for the password before roles can be granted', function () {
    $owner = User::factory()->create();
    Merchant::factory()->withMember($owner)->create();

    $this->actingAs($owner)->get(route('portal.team'))->assertRedirect(route('password.confirm'));
});

test('a password change signs out sessions that were opened with the old password', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('profile.edit'))->assertOk();

    $user->forceFill(['password' => 'Changed-elsewhere-123!'])->save();

    $this->get(route('profile.edit'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('invitations sent by a member stop working once that member is removed or demoted', function (string $change) {
    Notification::fake();
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $merchant = Merchant::factory()->withMember($owner)->withMember($admin, MerchantRole::Admin)->create();
    $token = inviteAs($merchant, $admin, 'alias@example.test', MerchantRole::Admin);

    match ($change) {
        'removed' => app(UpdateTeamMember::class)->remove($merchant, $admin, $owner),
        'demoted' => app(UpdateTeamMember::class)->changeRole($merchant, $admin, MerchantRole::Viewer, $owner),
    };

    $alias = User::factory()->create(['email' => 'alias@example.test']);

    expect(app(AcceptTeamInvitation::class)->handle($alias, $token))->toBeNull()
        ->and($alias->fresh()->roleIn($merchant))->toBeNull();
})->with(['removed', 'demoted']);

test('a removed member cannot keep reading a payment through an open page', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $merchant = Merchant::factory()->withMember($owner)->withMember($member, MerchantRole::Viewer)->create();
    $payment = Payment::factory()->for($merchant)->create(['livemode' => false]);
    $this->actingAs($member);
    app()->instance(GatewayAccount::class, new GatewayAccount($merchant, false));
    $page = Livewire::test('pages::portal.payments.show', ['payment' => $payment])->assertOk();

    app(UpdateTeamMember::class)->remove($merchant, $member->fresh(), $owner);
    app()->instance(GatewayAccount::class, new GatewayAccount(Merchant::factory()->withMember($member->fresh())->create(), false));

    $page->call('$refresh')->assertNotFound();
});

test('sign-up, password reset and password confirmation are rate limited', function (string $route, bool $signedIn) {
    if ($signedIn) {
        $this->actingAs(User::factory()->create());
    }

    foreach (range(1, 5) as $attempt) {
        $this->post(route($route), ['email' => "nobody{$attempt}@example.test", 'password' => 'wrong'])->assertStatus(302);
    }

    $this->post(route($route), ['email' => 'nobody6@example.test', 'password' => 'wrong'])->assertStatus(429);
})->with([
    'sign-up' => ['register.store', false],
    'password reset' => ['password.email', false],
    'password confirmation' => ['password.confirm.store', true],
]);

test('password reset emails to one address are capped per hour', function () {
    foreach (range(1, 3) as $ignored) {
        $this->post(route('password.email'), ['email' => 'victim@example.test'])->assertStatus(302);
    }

    $this->post(route('password.email'), ['email' => 'victim@example.test'])->assertStatus(429);
});

test('only the application host is trusted', function (string $host, bool $trusted) {
    config(['app.url' => 'https://pay.example.test']);
    $middleware = new class(app()) extends TrustHosts
    {
        protected function shouldSpecifyTrustedHosts()
        {
            return true;
        }
    };
    $request = Request::create("https://{$host}/forgot-password", 'POST');
    $middleware->handle($request, fn () => response('ok'));

    $trusted
        ? expect($request->getHost())->toBe($host)
        : expect(fn () => $request->getHost())->toThrow(SuspiciousOperationException::class);
})->with([
    'own host' => ['pay.example.test', true],
    'own subdomain' => ['api.pay.example.test', true],
    'host with the app host as a prefix' => ['pay.example.test.attacker.test', false],
    'look-alike host' => ['payxexample.test', false],
]);

test('test webhooks are rate limited per merchant', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $merchant = Merchant::factory()->withMember($owner)->create();
    WebhookEndpoint::factory()->for($merchant)->create();
    $this->actingAs($owner)->withSession(['auth.password_confirmed_at' => time()]);
    app()->instance(GatewayAccount::class, new GatewayAccount($merchant, false));
    $page = Livewire::test('pages::portal.developers.webhooks');

    foreach (range(1, 7) as $ignored) {
        $page->call('sendTest');
    }

    expect(WebhookDelivery::count())->toBe(5);
});

test('failing a payment never overwrites one that another path already captured', function () {
    Queue::fake();
    $payment = Payment::factory()->create(['status' => PaymentStatus::InProcess]);
    $staleCopy = Payment::query()->find($payment->id);

    $payment->forceFill(['status' => PaymentStatus::Successful])->save();
    app(FailPayment::class)->handle($staleCopy, 'Verification was not completed in time.');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Successful);
});

test('a member who cannot manage business documents gets no upload link', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $merchant = Merchant::factory()->withMember($owner)->withMember($viewer, MerchantRole::Viewer)->create();
    $this->actingAs($viewer)->withSession(['auth.password_confirmed_at' => time()]);
    app()->instance(GatewayAccount::class, new GatewayAccount($merchant, false));

    Livewire::test('pages::portal.business')
        ->call('_startUpload', 'documentFile', [['name' => 'a.pdf', 'size' => 1024, 'type' => 'application/pdf']], false)
        ->assertForbidden();
});

test('abandoned temporary uploads are purged after a day', function () {
    Storage::fake(config('filesystems.default'));
    $disk = Storage::disk(config('filesystems.default'));
    $disk->put('livewire-tmp/old.pdf', 'x');
    $disk->put('livewire-tmp/new.pdf', 'x');
    touch($disk->path('livewire-tmp/old.pdf'), now()->subDays(2)->getTimestamp());

    $this->artisan('gateway:purge-temporary-uploads')->assertSuccessful();

    expect($disk->exists('livewire-tmp/old.pdf'))->toBeFalse()
        ->and($disk->exists('livewire-tmp/new.pdf'))->toBeTrue();
});
