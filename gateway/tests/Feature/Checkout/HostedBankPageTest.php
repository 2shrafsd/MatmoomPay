<?php

use App\Enums\CheckoutPaymentStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Jobs\RefundUnappliedPayment;
use App\Models\ApiKey;
use App\Models\CheckoutSession;
use App\Models\Customer;
use App\Models\HostedPaymentAttempt;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Refund;
use App\Services\Payments\Processors\SandboxHostedProcessor;
use App\Support\Money;
use Database\Factories\ApiKeyFactory;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    config(['gateway.sandbox.flow' => 'hosted']);
    $this->merchant = Merchant::factory()->create();
    ApiKey::factory()->for($this->merchant)->publishable()->create();
    $this->publishableKey = ApiKeyFactory::$lastToken;
    $this->session = CheckoutSession::factory()->for($this->merchant)->create(['total_amount' => 12_000]);
});

/**
 * Walks the cardholder through the simulated bank and returns the signed return URL it sends back.
 */
function payAtBank($test, CheckoutSession $session, string $card = '4242424242424242', string $code = '1234', array $extra = []): string
{
    $test->get(route('checkout.show', ['session' => $session, 'key' => $test->publishableKey]))->assertOk()->assertSee('data-test="pay-at-bank"', false);

    $bankUrl = $test->post(route('checkout.hosted', $session), $extra)->assertRedirect()->headers->get('Location');
    parse_str((string) parse_url($bankUrl, PHP_URL_QUERY), $order);

    $test->get($bankUrl)->assertOk()->assertSee(Money::format($session->total_amount));
    $test->post(route('sandbox-bank.card'), $order + ['card_number' => $card, 'expiry' => now()->addYear()->format('m/y'), 'cvc' => '123', 'cardholder_name' => 'Noor'])->assertOk();

    return $test->post(route('sandbox-bank.otp'), $order + ['code' => $code])->assertRedirect()->headers->get('Location');
}

test('the card form is replaced by the bank page and an approved payment completes the session', function () {
    $returnUrl = payAtBank($this, $this->session);

    expect($returnUrl)->toStartWith(route('checkout.return', HostedPaymentAttempt::sole()));
    expect(Payment::count())->toBe(0);

    $this->get($returnUrl)->assertRedirect('https://shop.example/success');

    $payment = Payment::sole();
    expect($payment->status)->toBe(PaymentStatus::Successful)
        ->and($payment->amount)->toBe(12_000)
        ->and($payment->card_last_four)->toBe('4242')
        ->and($this->session->refresh()->payment_status)->toBe(CheckoutPaymentStatus::Paid);
});

test('the checkout page never shows a card number field in hosted mode', function () {
    $this->get(route('checkout.show', ['session' => $this->session, 'key' => $this->publishableKey]))
        ->assertOk()
        ->assertDontSee('name="card_number"', false);
});

test('a declined card comes back as a failed payment and the session stays payable', function () {
    $returnUrl = payAtBank($this, $this->session, '4000000000000002');

    $this->get($returnUrl)->assertRedirect(route('checkout.show', $this->session))->assertSessionHasErrors('payment');

    expect(Payment::sole()->status)->toBe(PaymentStatus::Failed)
        ->and($this->session->refresh()->isPayable())->toBeTrue();
});

test('a tampered bank answer is rejected', function () {
    $returnUrl = payAtBank($this, $this->session);

    $this->get(preg_replace('/s=[a-f0-9]+/', 's='.str_repeat('0', 64), $returnUrl))->assertStatus(400);

    expect(Payment::count())->toBe(0);
});

test('a correctly signed answer for a different amount is rejected', function () {
    $this->get(route('checkout.show', ['session' => $this->session, 'key' => $this->publishableKey]));
    $this->post(route('checkout.hosted', $this->session));
    $attempt = HostedPaymentAttempt::sole();

    $forged = SandboxHostedProcessor::encode(['ref' => $attempt->public_id, 'amount' => 1, 'currency' => 'SDG', 'approved' => true, 'txn' => 'x', 'ts' => now()->getTimestamp(), 'card' => [
        'token' => '', 'fingerprint' => str_repeat('a', 64), 'bin' => '424242', 'last4' => '4242', 'brand' => 'Visa', 'type' => 'Debit', 'exp_month' => 1, 'exp_year' => 2030,
    ]]);

    $this->get(route('checkout.return', $attempt).'?'.http_build_query(['r' => $forged, 's' => SandboxHostedProcessor::sign($forged)]))->assertStatus(400);

    expect(Payment::count())->toBe(0)->and($this->session->refresh()->isPayable())->toBeTrue();
});

test('replaying the bank answer never creates a second payment', function () {
    $returnUrl = payAtBank($this, $this->session);

    $this->get($returnUrl)->assertRedirect('https://shop.example/success');
    $this->get($returnUrl);

    expect(Payment::count())->toBe(1);
});

test('an old bank answer is refused as a replay', function () {
    $returnUrl = payAtBank($this, $this->session);

    $this->travel(SandboxHostedProcessor::RESULT_TTL + 60)->seconds();

    $this->get($returnUrl)->assertStatus(400);
    expect(Payment::count())->toBe(0);
});

test('a second approved attempt on an already paid session is refunded automatically', function () {
    $first = payAtBank($this, $this->session);
    $second = payAtBank($this, $this->session);

    $this->get($first);
    $this->get($second);

    expect(Payment::where('status', PaymentStatus::Successful)->count())->toBe(2);

    Queue::assertPushed(RefundUnappliedPayment::class, 1);
    Queue::pushed(RefundUnappliedPayment::class)->each(fn (RefundUnappliedPayment $job) => app()->call([$job, 'handle']));

    expect(Refund::sole())
        ->amount->toBe(12_000)
        ->status->toBe(RefundStatus::Successful)
        ->and(Payment::query()->latest('id')->first()->refunded_amount)->toBe(12_000);
});

test('the card is saved when the customer asks for it at the bank page flow', function () {
    $customer = Customer::factory()->for($this->merchant)->create();
    $session = CheckoutSession::factory()->for($this->merchant)->for($customer)->create();

    $this->get(payAtBank($this, $session, extra: ['save_card' => '1']));

    expect(PaymentMethod::sole()->customer_id)->toBe($customer->id);
});

test('the simulated bank is closed when the sandbox uses the direct flow', function () {
    config(['gateway.sandbox.flow' => 'direct']);

    $this->get(route('sandbox-bank.show', ['p' => 'x', 's' => 'y']))->assertNotFound();
});

test('an unsigned order cannot open the simulated bank', function () {
    $payload = SandboxHostedProcessor::encode(['ref' => 'hpa_x', 'amount' => 1, 'currency' => 'SDG', 'description' => 'x', 'return' => 'https://evil.example', 'cancel' => 'https://evil.example', 'locale' => 'en', 'exp' => now()->addHour()->getTimestamp()]);

    $this->get(route('sandbox-bank.show', ['p' => $payload, 's' => 'forged']))->assertStatus(400);
});
