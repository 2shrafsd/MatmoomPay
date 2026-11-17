<?php

use App\Enums\MerchantRole;
use App\Enums\MerchantStatus;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\User;
use Livewire\Livewire;

function owner(): User
{
    $admin = User::factory()->withTwoFactor()->create();
    $admin->forceFill(['is_platform_admin' => true])->save();

    return $admin;
}

test('the users page renders for the platform owner', function () {
    $this->actingAs(owner())->get(route('admin.users'))->assertOk()->assertSee(__('Users'));
});

test('banning a merchant revokes every key and blocks the api in both modes', function () {
    $merchant = Merchant::factory()->create();
    [$testKey, $headers] = apiKeyFor($merchant);
    $liveKey = ApiKey::factory()->for($merchant)->live()->create();

    $this->actingAs(owner());

    Livewire::test('pages::admin.merchants.show', ['merchant' => $merchant])
        ->call('changeStatus', 'banned')
        ->assertHasErrors('reason')
        ->set('reason', 'Selling counterfeit goods')
        ->call('changeStatus', 'banned');

    expect($merchant->refresh()->status)->toBe(MerchantStatus::Banned)
        ->and($testKey->refresh()->isRevoked())->toBeTrue()
        ->and($liveKey->refresh()->isRevoked())->toBeTrue();

    $this->postJson('/api/v1/checkout/session', checkoutPayload(), $headers)->assertUnauthorized();
});

test('the team of a banned merchant is shut out of the portal', function () {
    $member = User::factory()->create();
    Merchant::factory()->banned()->withMember($member)->create();

    $this->actingAs($member)->get(route('dashboard'))->assertForbidden();
});

test('a member of a banned merchant lands in another business they belong to', function () {
    $member = User::factory()->create();
    $healthy = Merchant::factory()->withMember($member)->create();
    Merchant::factory()->banned()->withMember($member)->create();

    $this->actingAs($member)->get(route('dashboard'))->assertOk();

    expect($member->refresh()->current_merchant_id)->toBe($healthy->id);
});

test('the owner edits merchant details from the admin panel', function () {
    $merchant = Merchant::factory()->create();

    $this->actingAs(owner());

    Livewire::test('pages::admin.merchants.show', ['merchant' => $merchant])
        ->set('profile.name', 'Nile Spices')
        ->set('profile.email', 'not-an-email')
        ->call('updateProfile')
        ->assertHasErrors('profile.email')
        ->set('profile.email', 'hello@nilespices.sd')
        ->call('updateProfile')
        ->assertHasNoErrors();

    expect($merchant->refresh())
        ->name->toBe('Nile Spices')
        ->email->toBe('hello@nilespices.sd');
});

test('archiving hides the merchant, stops its keys, and restoring brings it back', function () {
    $member = User::factory()->create();
    $merchant = Merchant::factory()->withMember($member)->create();
    [$key, $headers] = apiKeyFor($merchant);

    $this->actingAs(owner());

    Livewire::test('pages::admin.merchants.index')
        ->call('askReason', $merchant->public_id, 'archive')
        ->set('reason', 'Closed the business')
        ->call('confirmWithReason');

    expect($merchant->refresh()->trashed())->toBeTrue()
        ->and($key->refresh()->isRevoked())->toBeTrue()
        ->and(AuditLog::where('action', 'merchant.archived')->exists())->toBeTrue();

    $this->get(route('admin.merchants.show', $merchant))->assertOk();
    $this->postJson('/api/v1/checkout/session', checkoutPayload(), $headers)->assertUnauthorized();

    Livewire::test('pages::admin.merchants.index')->call('restore', $merchant->public_id);

    expect($merchant->refresh()->trashed())->toBeFalse();
});

test('a merchant with payment history cannot be deleted permanently', function () {
    $merchant = Merchant::factory()->create();
    Payment::factory()->for($merchant)->create();

    $this->actingAs(owner());

    Livewire::test('pages::admin.merchants.show', ['merchant' => $merchant])->call('destroy');

    expect(Merchant::withTrashed()->whereKey($merchant->id)->exists())->toBeTrue();
});

test('a merchant that never touched money is deleted permanently', function () {
    $merchant = Merchant::factory()->create();
    ApiKey::factory()->for($merchant)->create();

    $this->actingAs(owner());

    Livewire::test('pages::admin.merchants.show', ['merchant' => $merchant])
        ->call('destroy')
        ->assertRedirect(route('admin.merchants'));

    expect(Merchant::withTrashed()->whereKey($merchant->id)->exists())->toBeFalse()
        ->and(AuditLog::where('action', 'merchant.deleted')->exists())->toBeTrue();
});

test('the owner removes a team member but never the business owner', function () {
    $owner = User::factory()->create();
    $developer = User::factory()->create();
    $merchant = Merchant::factory()->withMember($owner)->create();
    $merchant->users()->attach($developer->id, ['role' => MerchantRole::Developer->value]);

    $this->actingAs(owner());

    Livewire::test('pages::admin.merchants.show', ['merchant' => $merchant])
        ->call('removeMember', $owner->id)
        ->call('removeMember', $developer->id);

    expect($merchant->roleFor($owner))->toBe(MerchantRole::Owner)
        ->and($merchant->roleFor($developer))->toBeNull();
});

test('a banned user is signed out and cannot sign in again', function () {
    $admin = owner();
    $person = User::factory()->create();

    $this->actingAs($admin);

    Livewire::test('pages::admin.users.index')
        ->call('askBan', $person->id)
        ->call('ban')
        ->assertHasErrors('banReason')
        ->set('banReason', 'Card testing from this account')
        ->call('ban');

    expect($person->refresh()->isBanned())->toBeTrue();

    $this->actingAs($person)->get(route('profile.edit'))->assertRedirect(route('login'));
    $this->assertGuest();

    $this->post(route('login.store'), ['email' => $person->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('lifting a ban lets the person sign in', function () {
    $person = User::factory()->banned()->create();

    $this->actingAs(owner());
    Livewire::test('pages::admin.users.index')->call('unban', $person->id);

    expect($person->refresh()->isBanned())->toBeFalse();
});

test('the owner cannot ban, demote or delete their own account', function () {
    $admin = owner();

    $this->actingAs($admin);

    Livewire::test('pages::admin.users.index')
        ->call('askBan', $admin->id)
        ->set('banReason', 'Testing')
        ->call('ban')
        ->call('setAdmin', $admin->id, false)
        ->call('delete', $admin->id);

    $admin->refresh();
    expect($admin->isBanned())->toBeFalse()
        ->and($admin->isPlatformAdmin())->toBeTrue()
        ->and($admin->exists)->toBeTrue();
});

test('the only owner of a business cannot be deleted', function () {
    $person = User::factory()->create();
    Merchant::factory()->withMember($person)->create();

    $this->actingAs(owner());
    Livewire::test('pages::admin.users.index')->call('delete', $person->id);

    expect(User::whereKey($person->id)->exists())->toBeTrue();
});

test('the owner edits a user, grants admin rights and resets two-factor', function () {
    $person = User::factory()->withTwoFactor()->create();

    $this->actingAs(owner());

    Livewire::test('pages::admin.users.index')
        ->call('edit', $person->id)
        ->set('name', 'Mona Osman')
        ->set('email', 'mona@nilespices.sd')
        ->call('saveUser')
        ->assertHasNoErrors()
        ->call('setAdmin', $person->id, true)
        ->call('resetTwoFactor', $person->id);

    $person->refresh();
    expect($person->name)->toBe('Mona Osman')
        ->and($person->email)->toBe('mona@nilespices.sd')
        ->and($person->email_verified_at)->toBeNull()
        ->and($person->isPlatformAdmin())->toBeTrue()
        ->and($person->two_factor_confirmed_at)->toBeNull();
});

test('regular users cannot reach the users page', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.users'))->assertNotFound();
});
