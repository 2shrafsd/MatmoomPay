<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\MobileReturnController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShopController::class, 'index'])->name('shop.index');

Route::get('cart', [CartController::class, 'show'])->name('cart.show');
Route::post('cart/{product}', [CartController::class, 'add'])->name('cart.add');
Route::patch('cart/{product}', [CartController::class, 'update'])->name('cart.update');

Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout');

Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
Route::get('orders/{order}/return', [OrderController::class, 'return'])->name('orders.return');

// The payment page sends mobile app customers here; the app's WebView watches for these URLs.
Route::get('m/orders/{order}/return', [MobileReturnController::class, 'return'])->name('mobile.return');
Route::get('m/orders/{order}/cancel', [MobileReturnController::class, 'cancel'])->name('mobile.cancel');

Route::post('webhooks/matmoom', WebhookController::class)->name('webhooks.matmoom');
