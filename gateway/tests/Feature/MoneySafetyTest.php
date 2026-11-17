<?php

use App\Actions\Checkout\OpenCheckoutPayment;
use App\Actions\Payments\FailPayment;
use App\Actions\Payments\FinalizeSuccessfulPayment;
use App\Actions\Subscriptions\RenewSubscription;
use App\Enums\ChallengeType;
use App\Enums\CheckoutPaymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Exceptions\GatewayException;
use App\Jobs\RefundUnappliedPayment;
use App\Models\CheckoutSession;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\PaymentLink;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Services\Payments\Contracts\PaymentProcessor;
use App\Services\Payments\Data\CardDetails;
use App\Services\Payments\Data\CardToken;
use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Data\ChargeResult;
use App\Services\Payments\Data\RefundResult;
use App\Services\Payments\Exceptions\ProcessorUnavailable;
use App\Services\Payments\Processors\SandboxProcessor;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());

/**
 * A sandbox stand-in whose answers each test scripts: null means "the bank did not answer".
 */
function scriptedProcessor(?ChargeResult $charge = null, ?ChargeResult $retrieve = null, bool $refundTimesOut = false): object
{
    $processor = new class implements PaymentProcessor
    {
        public ?ChargeResult $charge = null;

        public ?ChargeResult $retrieve = null;

        public bool $refundTimesOut = false;

        public function name(): string
        {
            return 'sandbox';
        }

        public function tokenize(CardDetails $card): CardToken
        {
            return app(SandboxProcessor::class)->tokenize($card);
        }

        public function charge(ChargeRequest $request): ChargeResult
        {
            return $this->charge ?? throw new ProcessorUnavailable;
        }

        public function completeChallenge(string $token, string $processorReference, ChallengeType $type, string $code): ChargeResult
        {
            return ChargeResult::succeeded($processorReference);
        }

        public function refund(string $processorReference, int $amount, string $currency, string $reference): RefundResult
        {
            return $this->refundTimesOut ? throw new ProcessorUnavailable : new RefundResult(true, 'rf_'.$reference);
        }

        public function retrieve(string $reference): ?ChargeResult
        {
            return $this->retrieve;
        }
    };

    $processor->charge = $charge;
    $processor->retrieve = $retrieve;
    $processor->refundTimesOut = $refundTimesOut;
    app()->instance(SandboxProcessor::class, $processor);

    return $processor;
}

function paymentBeingCharged(array $attributes = []): Payment
{
    return Payment::factory()->create([
        'status' => PaymentStatus::InProcess,
        'fee' => 0,
        'completed_at' => null,
        'processor_reference' => null,
        ...$attributes,
    ]);
}

test('a second pay click cannot replace a charge the bank is still processing', function () {
    $session = CheckoutSession::factory()->create();
    $card = PaymentMethod::factory()->for(Customer::factory()->for($session->merchant))->create();
    $inFlight = paymentBeingCharged(['merchant_id' => $session->merchant_id, 'checkout_session_id' => $session->id]);

    expect(fn () => app(OpenCheckoutPayment::class)->handle($session, $card, 'sandbox', false, null))
        ->toThrow(GatewayException::class, 'already being processed');

    expect($inFlight->refresh()->status)->toBe(PaymentStatus::InProcess)
        ->and(Payment::count())->toBe(1);
});

test('a payment still waiting for its verification code may be replaced by a new attempt', function () {
    $session = CheckoutSession::factory()->create();
    $card = PaymentMethod::factory()->for(Customer::factory()->for($session->merchant))->create();
    $waiting = paymentBeingCharged(['merchant_id' => $session->merchant_id, 'checkout_session_id' => $session->id, 'challenge_type' => ChallengeType::Otp]);

    app(OpenCheckoutPayment::class)->handle($session, $card, 'sandbox', false, null);

    expect($waiting->refresh()->status)->toBe(PaymentStatus::Failed)
        ->and(Payment::count())->toBe(2);
});

test('a session or intent cannot be cancelled while its charge is in flight', function () {
    [$key, $headers] = apiKeyFor();
    $session = CheckoutSession::factory()->for($key->merchant)->create();
    paymentBeingCharged(['merchant_id' => $key->merchant_id, 'checkout_session_id' => $session->id]);
    $intent = PaymentIntent::factory()->for($key->merchant)->create();
    paymentBeingCharged(['merchant_id' => $key->merchant_id, 'payment_intent_id' => $intent->id]);

    $this->postJson("/api/v1/checkout/{$session->public_id}/cancel", [], $headers)->assertStatus(409);
    $this->postJson("/api/v1/payment_intents/{$intent->public_id}/cancel", [], $headers)->assertStatus(409);

    expect($session->refresh()->payment_status)->toBe(CheckoutPaymentStatus::Unpaid)
        ->and($intent->refresh()->status)->not->toBe(PaymentIntentStatus::Cancelled);
});

test('confirming an intent twice never creates a second charge', function () {
    [$key, $headers] = apiKeyFor();
    $card = PaymentMethod::factory()->for(Customer::factory()->for($key->merchant))->create();
    $intent = PaymentIntent::factory()->for($key->merchant)->create(['payment_method_id' => $card->id]);
    paymentBeingCharged(['merchant_id' => $key->merchant_id, 'payment_intent_id' => $intent->id]);

    $this->postJson("/api/v1/payment_intents/{$intent->public_id}/confirm", [], $headers)
        ->assertStatus(409)
        ->assertJsonPath('code', 4101);

    expect(Payment::count())->toBe(1);
});

test('a failed payment never reopens an intent that already succeeded', function () {
    $intent = PaymentIntent::factory()->create(['status' => PaymentIntentStatus::Succeeded]);
    $late = paymentBeingCharged(['merchant_id' => $intent->merchant_id, 'payment_intent_id' => $intent->id]);

    app(FailPayment::class)->handle($late, 'Declined by the issuing bank.');

    expect($intent->refresh()->status)->toBe(PaymentIntentStatus::Succeeded);
});

test('money captured for a cancelled session is recorded and refunded, and the session stays cancelled', function () {
    $session = CheckoutSession::factory()->create(['payment_status' => CheckoutPaymentStatus::Cancelled]);
    $payment = paymentBeingCharged(['merchant_id' => $session->merchant_id, 'checkout_session_id' => $session->id, 'amount' => $session->total_amount]);

    app(FinalizeSuccessfulPayment::class)->handle($payment);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Successful)
        ->and($session->refresh()->payment_status)->toBe(CheckoutPaymentStatus::Cancelled);
    Queue::assertPushed(RefundUnappliedPayment::class, fn ($job) => $job->payment->is($payment));
});

test('a payment link cannot be paid more times than its limit', function () {
    $link = PaymentLink::factory()->create(['max_uses' => 1, 'uses_count' => 1]);
    $session = CheckoutSession::factory()->for($link->merchant)->create(['payment_link_id' => $link->id]);
    $payment = paymentBeingCharged(['merchant_id' => $link->merchant_id, 'checkout_session_id' => $session->id]);

    app(FinalizeSuccessfulPayment::class)->handle($payment);

    expect($link->refresh()->uses_count)->toBe(1)
        ->and($session->refresh()->payment_status)->toBe(CheckoutPaymentStatus::Unpaid);
    Queue::assertPushed(RefundUnappliedPayment::class);
});

test('a charge the bank did not answer stays in process and reconciliation settles it', function () {
    [$key, $headers] = apiKeyFor();
    $card = PaymentMethod::factory()->for(Customer::factory()->for($key->merchant))->create();
    $intent = PaymentIntent::factory()->for($key->merchant)->create(['payment_method_id' => $card->id]);
    $processor = scriptedProcessor();

    $this->postJson("/api/v1/payment_intents/{$intent->public_id}/confirm", [], $headers)->assertStatus(503);

    $payment = Payment::sole();
    expect($payment->status)->toBe(PaymentStatus::InProcess);

    $processor->retrieve = ChargeResult::succeeded('bank_txn_1');
    $this->travel(3)->minutes();
    $this->artisan('gateway:reconcile')->assertSuccessful();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Successful)
        ->and($payment->processor_reference)->toBe('bank_txn_1')
        ->and($intent->refresh()->status)->toBe(PaymentIntentStatus::Succeeded);
});

test('reconciliation fails a charge the bank never received', function () {
    scriptedProcessor();
    $payment = paymentBeingCharged(['created_at' => now()->subMinutes(5)]);

    $this->artisan('gateway:reconcile')->assertSuccessful();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->failure_reason)->toBe('The bank has no record of this payment.');
});

test('an abandoned verification is closed after its window', function () {
    $payment = paymentBeingCharged(['challenge_type' => ChallengeType::Otp, 'updated_at' => now()->subHour()]);

    $this->artisan('gateway:reconcile')->assertSuccessful();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed);
});

test('a refund the bank did not confirm stays pending, holds the balance, and is resent with the same reference', function () {
    [$key, $headers] = apiKeyFor();
    $payment = Payment::factory()->for($key->merchant)->create(['amount' => 9_000]);
    $processor = scriptedProcessor(refundTimesOut: true);

    $this->postJson('/api/v1/refunds', ['payment_id' => $payment->public_id, 'reason' => 'Damaged', 'metadata' => []], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');

    $this->postJson('/api/v1/refunds', ['payment_id' => $payment->public_id, 'reason' => 'Again', 'metadata' => []], $headers)
        ->assertStatus(400)
        ->assertJsonPath('code', 4301);

    $processor->refundTimesOut = false;
    $this->travel(3)->minutes();
    $this->artisan('gateway:reconcile')->assertSuccessful();

    $refund = Refund::sole();
    expect($refund->status)->toBe(RefundStatus::Successful)
        ->and($refund->processor_reference)->toBe('rf_'.$refund->public_id)
        ->and($payment->refresh()->refunded_amount)->toBe(9_000);
});

test('a renewal is not charged again while the previous charge has no answer', function () {
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->for($customer->merchant)->create();
    $subscription = Subscription::factory()->for($plan)->create([
        'merchant_id' => $customer->merchant_id,
        'customer_id' => $customer->id,
        'payment_method_id' => PaymentMethod::factory()->for($customer)->create()->id,
    ]);
    $intent = PaymentIntent::factory()->for($customer->merchant)->create();
    paymentBeingCharged(['merchant_id' => $customer->merchant_id, 'payment_intent_id' => $intent->id]);
    SubscriptionInvoice::create([
        'subscription_id' => $subscription->id,
        'merchant_id' => $customer->merchant_id,
        'livemode' => false,
        'amount_due' => $plan->amount,
        'currency' => 'SDG',
        'status' => InvoiceStatus::Open,
        'attempt_count' => 1,
        'period_start' => $subscription->current_period_end,
        'period_end' => $subscription->current_period_end->addMonth(),
        'payment_intent_id' => $intent->id,
    ]);

    app(RenewSubscription::class)->handle($subscription);

    expect(PaymentIntent::count())->toBe(1)->and(Payment::count())->toBe(1);
});

test('an idempotency key left by a crashed request stops blocking after five minutes', function () {
    [$key, $headers] = apiKeyFor();
    IdempotencyKey::create([
        'merchant_id' => $key->merchant_id,
        'livemode' => false,
        'key' => 'order-1001-create',
        'request_fingerprint' => hash('sha256', 'POST|api/v1/checkout/session|'.json_encode(checkoutPayload())),
        'expires_at' => now()->addDay(),
    ]);

    $this->postJson('/api/v1/checkout/session', checkoutPayload(), $headers + ['Idempotency-Key' => 'order-1001-create'])->assertStatus(409);

    $this->travel(6)->minutes();

    $this->postJson('/api/v1/checkout/session', checkoutPayload(), $headers + ['Idempotency-Key' => 'order-1001-create'])->assertOk();
});
