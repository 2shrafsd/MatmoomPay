<?php

use App\Enums\BalanceTransactionType;
use App\Models\BalanceTransaction;
use App\Models\Payment;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());

test('a successful payment is refunded in full by default', function () {
    [$key, $headers] = apiKeyFor();
    $payment = Payment::factory()->for($key->merchant)->create(['amount' => 9_500]);

    $this->postJson('/api/v1/refunds', ['payment_id' => $payment->public_id, 'reason' => 'Paid twice', 'metadata' => []], $headers)
        ->assertOk()
        ->assertJsonPath('code', 2001)
        ->assertJsonPath('data.amount', 9_500)
        ->assertJsonPath('data.status', 'successful')
        ->assertJsonPath('data.payment_id', $payment->public_id);

    expect($payment->refresh()->refunded_amount)->toBe(9_500)
        ->and(BalanceTransaction::where('type', BalanceTransactionType::Refund)->sum('amount'))->toEqual(-9_500);

    $this->getJson("/api/v1/payments/{$payment->public_id}", $headers)
        ->assertJsonPath('data.refunded', true)
        ->assertJsonCount(1, 'data.refunds');
});

test('partial refunds cannot exceed what was captured', function () {
    [$key, $headers] = apiKeyFor();
    $payment = Payment::factory()->for($key->merchant)->create(['amount' => 9_500]);

    $this->postJson('/api/v1/refunds', ['payment_id' => $payment->public_id, 'reason' => 'Late delivery', 'amount' => 4_000, 'metadata' => []], $headers)->assertOk();
    $this->postJson('/api/v1/refunds', ['payment_id' => $payment->public_id, 'reason' => 'Again', 'amount' => 6_000, 'metadata' => []], $headers)
        ->assertStatus(400)
        ->assertJsonFragment(['field' => 'amount']);
    $this->postJson('/api/v1/refunds', ['payment_id' => $payment->public_id, 'reason' => 'Rest', 'metadata' => []], $headers)->assertOk()->assertJsonPath('data.amount', 5_500);
    $this->postJson('/api/v1/refunds', ['payment_id' => $payment->public_id, 'reason' => 'More', 'metadata' => []], $headers)->assertStatus(400)->assertJsonPath('code', 4301);

    expect($payment->refresh()->refunded_amount)->toBe(9_500);
});

test('failed payments cannot be refunded', function () {
    [$key, $headers] = apiKeyFor();
    $payment = Payment::factory()->for($key->merchant)->failed()->create();

    $this->postJson('/api/v1/refunds', ['payment_id' => $payment->public_id, 'reason' => 'x', 'metadata' => []], $headers)
        ->assertStatus(400)
        ->assertJsonPath('code', 4300);
});

test('a merchant cannot refund another merchant payment', function () {
    [, $headers] = apiKeyFor();
    $foreign = Payment::factory()->create();

    $this->postJson('/api/v1/refunds', ['payment_id' => $foreign->public_id, 'reason' => 'x', 'metadata' => []], $headers)->assertNotFound();

    expect($foreign->refresh()->refunded_amount)->toBe(0);
});
