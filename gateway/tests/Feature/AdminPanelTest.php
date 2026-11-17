<?php

use App\Actions\Payments\FinalizeSuccessfulPayment;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\MerchantStatus;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function platformAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create();
    $admin->forceFill(['is_platform_admin' => true])->save();

    return $admin;
}

test('regular users get a 404 from the admin panel', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertNotFound();
});

test('admins without two-factor authentication are sent to enable it', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_platform_admin' => true])->save();

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('security.edit'));
});

test('admins without a merchant account land on the admin panel instead of onboarding', function () {
    $this->actingAs(platformAdmin())->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
});

test('the security page tells an admin why the admin panel sent them there', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_platform_admin' => true])->save();

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('The admin panel requires two-factor authentication.');
});

test('every admin page renders', function (string $route) {
    $merchant = Merchant::factory()->create();

    $this->actingAs(platformAdmin())
        ->get($route === 'admin.merchants.show' ? route($route, $merchant) : route($route))
        ->assertOk();
})->with(['admin.dashboard', 'admin.merchants', 'admin.merchants.show', 'admin.payments', 'admin.settlements', 'admin.audit']);

test('a merchant cannot be approved before its documents are accepted', function () {
    $merchant = Merchant::factory()->pending()->create();

    $this->actingAs(platformAdmin());

    Livewire::test('pages::admin.merchants.show', ['merchant' => $merchant])->call('changeStatus', 'active');

    expect($merchant->refresh()->status)->toBe(MerchantStatus::Pending);
});

test('approving a merchant opens live mode and is audited', function () {
    $admin = platformAdmin();
    $merchant = Merchant::factory()->pending()->create();

    foreach (DocumentType::required() as $type) {
        $merchant->documents()->create([
            'type' => $type, 'original_name' => 'doc.pdf', 'path' => 'kyc/doc.pdf', 'mime_type' => 'application/pdf', 'size' => 1, 'sha256' => str_repeat('0', 64),
        ])->forceFill(['status' => DocumentStatus::Approved])->save();
    }

    $this->actingAs($admin);

    Livewire::test('pages::admin.merchants.show', ['merchant' => $merchant])->call('changeStatus', 'active');

    $merchant->refresh();
    expect($merchant->status)->toBe(MerchantStatus::Active)
        ->and($merchant->approved_by)->toBe($admin->id)
        ->and(AuditLog::where('action', 'merchant.status_changed')->exists())->toBeTrue();
});

test('suspending needs a reason and revokes live keys', function () {
    $merchant = Merchant::factory()->create();
    $liveKey = ApiKey::factory()->for($merchant)->live()->create();

    $this->actingAs(platformAdmin());

    Livewire::test('pages::admin.merchants.show', ['merchant' => $merchant])
        ->call('changeStatus', 'suspended')
        ->assertHasErrors('reason')
        ->set('reason', 'Chargeback ratio above the agreed limit')
        ->call('changeStatus', 'suspended');

    expect($merchant->refresh()->status)->toBe(MerchantStatus::Suspended)
        ->and($liveKey->refresh()->isRevoked())->toBeTrue();
});

test('a settlement collects the unsettled live balance exactly once', function () {
    Queue::fake();
    $merchant = Merchant::factory()->withSettlementAccount()->create(['fee_rate_bps' => 200, 'fee_fixed' => 0]);
    $payment = Payment::factory()->for($merchant)->create(['livemode' => true, 'amount' => 10_000, 'status' => 'InProcess', 'card_token' => null]);
    app(FinalizeSuccessfulPayment::class)->handle($payment);

    $this->travel(3)->days();
    $this->actingAs(platformAdmin());

    Livewire::test('pages::admin.merchants.show', ['merchant' => $merchant])->call('settle')->call('settle');

    $settlement = Settlement::sole();
    expect($settlement->gross_amount)->toBe(10_000)
        ->and($settlement->fee_amount)->toBe(200)
        ->and($settlement->net_amount)->toBe(9_800)
        ->and($settlement->iban)->toBe('SD2129000501234001234567');
});
