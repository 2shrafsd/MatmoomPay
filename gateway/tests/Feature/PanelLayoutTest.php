<?php

use App\Models\Merchant;
use App\Models\User;

function panelAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create();
    $admin->forceFill(['is_platform_admin' => true])->save();

    return $admin;
}

test('an admin opens their profile inside the admin panel', function () {
    $this->actingAs(panelAdmin())
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('data-test="admin-nav-users"', false);
});

test('an admin who came from the merchant portal keeps the merchant sidebar', function () {
    $admin = panelAdmin();
    Merchant::factory()->withMember($admin)->create();

    $this->actingAs($admin)->get(route('dashboard'))->assertOk();

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('data-test="portal-nav-settings"', false)
        ->assertDontSee('data-test="admin-nav-users"', false);
});

test('visiting the admin panel switches shared pages back to it', function () {
    $admin = panelAdmin();
    Merchant::factory()->withMember($admin)->create();

    $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    $this->get(route('admin.dashboard'))->assertOk();

    $this->get(route('profile.edit'))->assertSee('data-test="admin-nav-users"', false);
});

test('a merchant opens settings inside the merchant portal', function () {
    $user = User::factory()->create();
    Merchant::factory()->withMember($user)->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('data-test="portal-nav-settings"', false)
        ->assertDontSee('data-test="admin-nav-users"', false);
});

test('password confirmation stays inside the dashboard', function () {
    $user = User::factory()->create();
    Merchant::factory()->withMember($user)->create();

    $this->actingAs($user)
        ->get(route('password.confirm'))
        ->assertOk()
        ->assertSee('data-test="confirm-password-panel"', false)
        ->assertSee('data-test="portal-nav-settings"', false);
});
