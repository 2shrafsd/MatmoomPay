<?php

use App\Http\Controllers\Checkout\HostedCheckoutController;
use App\Http\Controllers\Checkout\PaymentAuthenticationController;
use App\Http\Controllers\Checkout\SandboxBankController;
use App\Http\Controllers\DownloadMerchantDocumentController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PaymentLinks\PublicPaymentLinkController;
use App\Http\Controllers\Portal\ExportPaymentsController;
use App\Http\Controllers\Portal\ExportSettlementController;
use App\Http\Controllers\Portal\InvitationController;
use App\Http\Controllers\Portal\PortalContextController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::view('docs', 'pages.docs')->name('docs');
Route::post('locale', LocaleController::class)->name('locale.update');

/*
|--------------------------------------------------------------------------
| Hosted payment pages
|--------------------------------------------------------------------------
*/

Route::prefix('pay/{session}')->name('checkout.')->controller(HostedCheckoutController::class)->group(function () {
    Route::get('/', 'show')->name('show');
    Route::post('card', 'pay')->middleware('throttle:checkout-pay')->name('pay');
    Route::post('saved/{card}', 'payWithSavedCard')->middleware('throttle:checkout-pay')->name('pay-saved');
    Route::get('verify/{payment}', 'challenge')->name('verify');
    Route::post('verify/{payment}', 'verify')->middleware('throttle:checkout-verify')->name('verify.submit');
    Route::get('cancel', 'cancel')->name('cancel');
    Route::post('hosted', 'startHosted')->middleware('throttle:checkout-pay')->name('hosted');
});

Route::match(['get', 'post'], 'pay/return/{attempt}', [HostedCheckoutController::class, 'hostedReturn'])
    ->middleware('throttle:checkout-return')
    ->name('checkout.return');

Route::prefix('sandbox-bank')->name('sandbox-bank.')->controller(SandboxBankController::class)->group(function () {
    Route::get('/', 'show')->name('show');
    Route::post('card', 'card')->middleware('throttle:sandbox-bank')->name('card');
    Route::post('otp', 'otp')->middleware('throttle:sandbox-bank')->name('otp');
    Route::get('cancel', 'cancel')->name('cancel');
});

Route::view('subscriptions/authenticated', 'pages.checkout.authenticated')->name('subscriptions.authenticated');

Route::middleware('signed')->controller(PaymentAuthenticationController::class)->group(function () {
    Route::get('authenticate/{payment}', 'show')->name('authenticate.show');
    Route::post('authenticate/{payment}', 'submit')->middleware('throttle:checkout-verify')->name('authenticate.submit');
});

Route::prefix('l/{link}')->name('links.')->controller(PublicPaymentLinkController::class)->group(function () {
    Route::get('/', 'show')->name('show');
    Route::post('/', 'start')->middleware('throttle:payment-link')->name('start');
    Route::get('thanks', 'thanks')->name('thanks');
});

/*
|--------------------------------------------------------------------------
| Merchant portal
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('onboarding', 'pages::portal.onboarding')->name('onboarding');
    Route::get('invitations/{token}', InvitationController::class)->name('invitations.accept');
    Route::get('documents/{document}', DownloadMerchantDocumentController::class)->middleware('throttle:30,1')->name('documents.download');

    Route::middleware(['merchant', 'panel:merchant'])->group(function () {
        Route::livewire('dashboard', 'pages::portal.dashboard')->name('dashboard');

        Route::post('portal/mode', [PortalContextController::class, 'mode'])->name('portal.mode');
        Route::post('portal/merchant/{merchant}', [PortalContextController::class, 'merchant'])->name('portal.merchant');

        Route::livewire('payments', 'pages::portal.payments.index')->name('portal.payments');
        Route::get('payments/export', ExportPaymentsController::class)->middleware('throttle:exports')->name('portal.payments.export');
        Route::livewire('payments/{payment}', 'pages::portal.payments.show')->name('portal.payments.show');
        Route::livewire('checkouts', 'pages::portal.checkouts')->name('portal.checkouts');
        Route::livewire('refunds', 'pages::portal.refunds')->name('portal.refunds');
        Route::livewire('customers', 'pages::portal.customers.index')->name('portal.customers');
        Route::livewire('customers/{customer}', 'pages::portal.customers.show')->name('portal.customers.show');
        Route::livewire('subscriptions', 'pages::portal.subscriptions')->name('portal.subscriptions');
        Route::livewire('plans', 'pages::portal.plans')->name('portal.plans');
        Route::livewire('links', 'pages::portal.links.index')->name('portal.links');
        Route::livewire('links/{link}', 'pages::portal.links.show')->name('portal.links.show');
        Route::livewire('balance', 'pages::portal.balance')->name('portal.balance');
        Route::livewire('settlements', 'pages::portal.settlements.index')->name('portal.settlements');
        Route::livewire('settlements/{settlement}', 'pages::portal.settlements.show')->name('portal.settlements.show');
        Route::get('settlements/{settlement}/export', ExportSettlementController::class)->middleware('throttle:exports')->name('portal.settlements.export');
        Route::livewire('team', 'pages::portal.team')->name('portal.team');

        Route::middleware('password.confirm')->group(function () {
            Route::livewire('developers', 'pages::portal.developers.keys')->name('portal.developers');
            Route::livewire('developers/webhooks', 'pages::portal.developers.webhooks')->name('portal.webhooks');
            Route::livewire('business', 'pages::portal.business')->name('portal.business');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Platform admin
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'platform.admin', 'panel:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');
    Route::livewire('merchants', 'pages::admin.merchants.index')->name('merchants');
    Route::livewire('merchants/{merchant}', 'pages::admin.merchants.show')->name('merchants.show')->withTrashed();
    Route::livewire('users', 'pages::admin.users.index')->name('users');
    Route::livewire('payments', 'pages::admin.payments')->name('payments');
    Route::livewire('settlements', 'pages::admin.settlements')->name('settlements');
    Route::livewire('audit', 'pages::admin.audit')->name('audit');
});

require __DIR__.'/settings.php';
