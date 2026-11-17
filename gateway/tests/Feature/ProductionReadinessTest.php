<?php

use App\Console\Commands\ReconcilePayments;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

test('the readiness check fails outside a hardened production environment', function () {
    config(['app.debug' => true]);

    $this->artisan('gateway:check-production')->assertFailed();
});

test('the readiness check passes with a hardened configuration', function () {
    $this->app['env'] = 'production';
    config([
        'app.debug' => false,
        'app.url' => 'https://pay.example.om',
        'session.encrypt' => true,
        'session.secure' => true,
        'queue.default' => 'redis',
        'mail.default' => 'smtp',
        'mail.from.address' => 'payments@pay.example.sd',
        'cache.default' => 'database',
        'logging.default' => 'single',
        'logging.channels.single.level' => 'info',
    ]);
    Cache::put(ReconcilePayments::HEARTBEAT_KEY, now()->getTimestamp());
    $admin = User::factory()->withTwoFactor()->create();
    $admin->forceFill(['is_platform_admin' => true])->save();

    $this->artisan('gateway:check-production')->assertSuccessful();
});

test('the readiness check fails when the scheduler is not running', function () {
    $this->app['env'] = 'production';
    config(['cache.default' => 'array']);

    $this->artisan('gateway:check-production')->assertFailed()->expectsOutputToContain('The scheduler ran in the last five minutes');
});
