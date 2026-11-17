<?php

use App\Actions\Merchants\UpdateMerchantProfile;
use App\Actions\PaymentIntents\ConfirmPaymentIntent;
use App\Actions\Payments\FinalizeSuccessfulPayment;
use App\Actions\Settlements\CreateSettlement;
use App\Enums\MerchantRole;
use App\Enums\SettlementStatus;
use App\Exceptions\GatewayException;
use App\Models\ApiKey;
use App\Models\BalanceTransaction;
use App\Models\CheckoutSession;
use App\Models\Merchant;
use App\Models\MerchantDocument;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\Settlement;
use App\Models\User;
use App\Notifications\SettlementAccountChanged;
use App\Notifications\TeamInvitation;
use App\Support\OutboundUrlGuard;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());

function liveBalance(Merchant $merchant, int $amount = 10_000): Payment
{
    $payment = Payment::factory()->for($merchant)->create(['livemode' => true, 'amount' => $amount, 'status' => 'InProcess', 'card_token' => null]);

    return app(FinalizeSuccessfulPayment::class)->handle($payment);
}

function reviewer(): User
{
    $admin = User::factory()->withTwoFactor()->create();
    $admin->forceFill(['is_platform_admin' => true])->save();

    return $admin;
}

test('a suspended merchant cannot collect on sessions that were already open', function () {
    $merchant = Merchant::factory()->create();
    ApiKey::factory()->for($merchant)->publishable()->create(['livemode' => true]);
    $session = CheckoutSession::factory()->for($merchant)->create(['livemode' => true]);
    $merchant->forceFill(['status' => 'suspended'])->save();

    expect($session->refresh()->canTakePayment())->toBeFalse();

    $intent = PaymentIntent::factory()->for($merchant)->create(['livemode' => true]);

    expect(fn () => app(ConfirmPaymentIntent::class)->handle($intent))
        ->toThrow(GatewayException::class, 'cannot take payments');
});

test('changing the payout account needs a new verification and emails every owner and admin', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $merchant = Merchant::factory()->withSettlementAccount()->withMember($owner)->create();
    $merchant->users()->attach($admin->id, ['role' => MerchantRole::Admin->value]);

    app(UpdateMerchantProfile::class)->updateSettlementAccount($merchant, 'Faisal Islamic Bank', 'Someone Else', 'SD9900000000000000000001', $admin);

    expect($merchant->refresh()->settlement_account_verified_at)->toBeNull();
    Notification::assertSentTo([$owner, $admin], SettlementAccountChanged::class);

    liveBalance($merchant);
    $this->travel(3)->days();

    expect(fn () => app(CreateSettlement::class)->handle($merchant, reviewer()))->toThrow(GatewayException::class, 'Verify');
});

test('saving the same payout account again changes nothing', function () {
    Notification::fake();
    $merchant = Merchant::factory()->withSettlementAccount()->create();

    app(UpdateMerchantProfile::class)->updateSettlementAccount($merchant, 'Bank of Khartoum', 'Nile Aroma LLC', 'SD2129000501234001234567', User::factory()->create());

    expect($merchant->refresh()->settlement_account_verified_at)->not->toBeNull();
    Notification::assertNothingSent();
});

test('payments wait out the hold period while refunds always count', function () {
    $merchant = Merchant::factory()->withSettlementAccount()->create(['fee_rate_bps' => 0]);
    liveBalance($merchant, 10_000);
    $this->travel(3)->days();
    liveBalance($merchant, 5_000);

    $settlement = app(CreateSettlement::class)->handle($merchant, reviewer());

    expect($settlement->gross_amount)->toBe(10_000)
        ->and(BalanceTransaction::whereNull('settlement_id')->sum('amount'))->toEqual(5_000);
});

test('a returned transfer releases its balance into the next settlement', function () {
    $merchant = Merchant::factory()->withSettlementAccount()->create(['fee_rate_bps' => 0]);
    liveBalance($merchant);
    $this->travel(3)->days();
    $admin = reviewer();
    $first = app(CreateSettlement::class)->handle($merchant, $admin);

    app(CreateSettlement::class)->markFailed($first, 'Account closed', $admin);
    $second = app(CreateSettlement::class)->handle($merchant, $admin);

    expect($first->refresh()->status)->toBe(SettlementStatus::Failed)
        ->and($second->net_amount)->toBe(10_000)
        ->and(fn () => app(CreateSettlement::class)->markPaid($first, 'REF-1', $admin))->toThrow(GatewayException::class);
});

test('an admin verifies a changed account from the merchant page', function () {
    $merchant = Merchant::factory()->withSettlementAccount()->create(['settlement_account_verified_at' => null]);
    $this->actingAs(reviewer());

    Livewire\Livewire::test('pages::admin.merchants.show', ['merchant' => $merchant])->call('verifySettlementAccount');

    expect($merchant->refresh()->hasVerifiedSettlementAccount())->toBeTrue();
    expect(Settlement::count())->toBe(0);
});

test('an admin without two-factor cannot download merchant identity documents', function () {
    $document = MerchantDocument::create([
        'merchant_id' => Merchant::factory()->create()->id,
        'type' => 'commercial_registration',
        'original_name' => 'cr.pdf',
        'path' => 'kyc/cr.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
        'sha256' => str_repeat('a', 64),
    ]);
    $admin = User::factory()->create();
    $admin->forceFill(['is_platform_admin' => true])->save();

    $this->actingAs($admin)->get(route('documents.download', $document))->assertNotFound();
});

test('webhooks cannot reach carrier-grade NAT, benchmarking or tunnelled IPv6 addresses', function (string $ip) {
    expect(OutboundUrlGuard::isPublic($ip))->toBeFalse();
})->with(['100.100.100.200', '198.18.0.1', '64:ff9b::7f00:1', '2002:7f00:1::', '::ffff:127.0.0.1']);

test('names in invitation emails cannot inject links', function () {
    $merchant = Merchant::factory()->create(['name' => '[Verify your card](https://evil.example)']);
    $mail = (new TeamInvitation($merchant, User::factory()->create(), 'token'))->toMail(User::factory()->create());

    expect(implode(' ', $mail->introLines))->not->toContain('[Verify your card](https://evil.example)');
});

test('unauthenticated API floods are rate limited per address before the key check', function () {
    config(['gateway.api.ip_rate_limit_per_minute' => 2]);

    $this->getJson('/api/v1/customers', ['matmoom-api-key' => 'sk_test_wrong'])->assertStatus(401);
    $this->getJson('/api/v1/customers', ['matmoom-api-key' => 'sk_test_wrong'])->assertStatus(401);
    $this->getJson('/api/v1/customers', ['matmoom-api-key' => 'sk_test_wrong'])->assertStatus(429);
});

test('the only owner of a business cannot delete their account', function () {
    $owner = User::factory()->create();
    Merchant::factory()->withMember($owner)->create(['name' => 'Nile Aroma']);

    $this->actingAs($owner);

    Livewire\Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasErrors('password');

    expect($owner->fresh())->not->toBeNull();
});
