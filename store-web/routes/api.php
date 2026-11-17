<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('products', [ProductController::class, 'index'])->name('api.products.index');

Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:10,1')->name('api.orders.store');
Route::get('orders/{order}', [OrderController::class, 'show'])->name('api.orders.show');
