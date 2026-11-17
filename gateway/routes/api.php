<?php

use App\Http\Controllers\Api\V1\CheckoutSessionController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentIntentController;
use App\Http\Controllers\Api\V1\PaymentMethodController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\RefundController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware([AssignRequestId::class, 'throttle:gateway-api-ip', AuthenticateApiKey::class, 'throttle:gateway-api', EnsureIdempotency::class])
    ->name('api.v1.')
    ->group(function () {
        Route::get('checkout/session', [CheckoutSessionController::class, 'index'])->name('checkout.index');
        Route::post('checkout/session', [CheckoutSessionController::class, 'store'])->name('checkout.store');
        Route::get('checkout/session/{sessionId}', [CheckoutSessionController::class, 'show'])->name('checkout.show');
        Route::get('checkout/reference/{clientReferenceId}', [CheckoutSessionController::class, 'showByReference'])->name('checkout.reference');
        Route::get('checkout/invoice/{invoice}', [CheckoutSessionController::class, 'showByInvoice'])->name('checkout.invoice');
        Route::post('checkout/{sessionId}/cancel', [CheckoutSessionController::class, 'cancel'])->name('checkout.cancel');

        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('customers/{customerId}', [CustomerController::class, 'show'])->name('customers.show');
        Route::delete('customers/{customerId}', [CustomerController::class, 'destroy'])->name('customers.destroy');

        Route::get('payment_methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
        Route::delete('payment_methods/{cardId}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');

        Route::get('payment_intents', [PaymentIntentController::class, 'index'])->name('payment-intents.index');
        Route::post('payment_intents', [PaymentIntentController::class, 'store'])->name('payment-intents.store');
        Route::get('payment_intents/{paymentIntentId}', [PaymentIntentController::class, 'show'])->name('payment-intents.show');
        Route::get('payment_intents/{clientReferenceId}/reference', [PaymentIntentController::class, 'showByReference'])->name('payment-intents.reference');
        Route::post('payment_intents/{paymentIntentId}/confirm', [PaymentIntentController::class, 'confirm'])->name('payment-intents.confirm');
        Route::post('payment_intents/{paymentIntentId}/cancel', [PaymentIntentController::class, 'cancel'])->name('payment-intents.cancel');

        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{paymentId}', [PaymentController::class, 'show'])->name('payments.show');

        Route::get('refunds', [RefundController::class, 'index'])->name('refunds.index');
        Route::post('refunds', [RefundController::class, 'store'])->name('refunds.store');
        Route::get('refunds/{refundId}', [RefundController::class, 'show'])->name('refunds.show');

        Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
        Route::post('plans', [PlanController::class, 'store'])->name('plans.store');
        Route::get('plans/{planId}', [PlanController::class, 'show'])->name('plans.show');

        Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::get('subscriptions/{subscriptionId}', [SubscriptionController::class, 'show'])->name('subscriptions.show');
        Route::get('subscriptions/{subscriptionId}/invoices', [SubscriptionController::class, 'invoices'])->name('subscriptions.invoices');
        Route::post('subscriptions/{subscriptionId}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
    });
