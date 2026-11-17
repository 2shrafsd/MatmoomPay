<?php

use App\Models\User;
use App\Notifications\EmailAddressChanged;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('profile.edit'))->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('current_password', 'password')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('changing the email address requires the current password', function (string $password) {
    $user = User::factory()->create(['email' => 'owner@example.test']);

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('email', 'attacker@example.test')
        ->set('current_password', $password)
        ->call('updateProfileInformation')
        ->assertHasErrors(['current_password']);

    expect($user->refresh()->email)->toBe('owner@example.test');
})->with(['missing' => '', 'wrong' => 'not-the-password']);

test('the previous address is told when the email address changes', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'owner@example.test']);

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('email', 'new@example.test')
        ->set('current_password', 'password')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    Notification::assertSentOnDemand(EmailAddressChanged::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'owner@example.test');
});

test('resending the verification email is rate limited', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user);
    $component = Livewire::test('pages::settings.profile');

    foreach (range(1, 8) as $ignored) {
        $component->call('resendVerificationNotification');
    }

    Notification::assertSentToTimes($user, VerifyEmail::class, 6);
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});
