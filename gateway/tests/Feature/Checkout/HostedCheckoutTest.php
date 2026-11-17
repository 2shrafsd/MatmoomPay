<?php

use App\Enums\BalanceTransactionType;
use App\Enums\CheckoutPaymentStatus;
use App\Enums\PaymentStatus;
use App\Models\ApiKey;
use App\Models\BalanceTransaction;
use App\Models\CheckoutSession;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Database\Factories\ApiKeyFactory;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->merchant = Merchant::factory()->create(['fee_rate_bps' => 250, 'fee_fixed' => 100]);
    ApiKey::factory()->for($this->merchant)->publishable()->create();
    $this->publishableKey = ApiKeyFactory::$lastToken;
    $this->session = CheckoutSession::factory()->for($this->merchant)->create(['total_amount' => 20_000]);
});

function openCheckout($test, CheckoutSession $session): void
{
    $test->get(route('checkout.show', ['session' => $session, 'key' => $test->publishableKey]))->assertOk();
}

function card(array $overrides = []): array
{
    return array_replace([
        'card_number' => '4242 4242 4242 4242',
        'expiry' => now()->addYear()->format('m/y'),
        'cvc' => '123',
        'cardholder_name' => 'Salim Al Harthy',
    ], $overrides);
}

test('the payment page needs a valid publishable key for the session merchant', function () {
    $this->get(route('checkout.show', $this->session))->assertNotFound();
    $this->get(route('checkout.show', ['session' => $this->session, 'key' => 'pk_test_wrong']))->assertNotFound();

    ApiKey::factory()->publishable()->create();
    $this->get(route('checkout.show', ['session' => $this->session, 'key' => ApiKeyFactory::$lastToken]))->assertNotFound();

    $this->get(route('checkout.show', ['session' => $this->session, 'key' => $this->publishableKey]))
        ->assertOk()
        ->assertSee($this->merchant->name)
        ->assertSee('20,000');
});

test('card payments cannot be posted without opening the page first', function () {
    $this->post(route('checkout.pay', $this->session), card())->assertNotFound();

    expect(Payment::count())->toBe(0);
});

test('an approved card asks for the otp, then captures and redirects to the success url', function () {
    WebhookEndpoint::factory()->for($this->merchant)->create();
    openCheckout($this, $this->session);

    $response = $this->post(route('checkout.pay', $this->session), card());

    $payment = Payment::sole();
    $response->assertRedirect(route('checkout.verify', [$this->session, $payment]));
    expect($payment->status)->toBe(PaymentStatus::InProcess);

    $this->get(route('checkout.verify', [$this->session, $payment]))->assertOk()->assertSee('4242 42XX XXXX 4242');

    $this->post(route('checkout.verify.submit', [$this->session, $payment]), ['code' => '1234'])
        ->assertRedirect('https://shop.example/success');

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::Successful)
        ->and($payment->fee)->toBe(600)
        ->and($payment->card_token)->toBeNull()
        ->and($this->session->refresh()->payment_status)->toBe(CheckoutPaymentStatus::Paid);

    expect(BalanceTransaction::where('type', BalanceTransactionType::Payment)->sum('amount'))->toEqual(20_000)
        ->and(BalanceTransaction::where('type', BalanceTransactionType::Fee)->sum('amount'))->toEqual(-600);

    expect(WebhookDelivery::pluck('event_type')->all())->toBe(['payment.pending', 'payment.succeeded', 'checkout.completed']);
});

test('the full card number and security code are never stored', function () {
    openCheckout($this, $this->session);
    $this->post(route('checkout.pay', $this->session), card());

    $stored = json_encode(Payment::sole()->getAttributes());

    expect($stored)->not->toContain('4242424242424242')
        ->and($stored)->toContain('424242')
        ->and(session()->getOldInput())->not->toHaveKey('card_number')
        ->and(session()->getOldInput())->not->toHaveKey('cvc');
});

test('a wrong code keeps the payment open and too many wrong codes fail it', function () {
    openCheckout($this, $this->session);
    $this->post(route('checkout.pay', $this->session), card());
    $payment = Payment::sole();

    $this->post(route('checkout.verify.submit', [$this->session, $payment]), ['code' => '0000'])->assertSessionHasErrors('code');
    expect($payment->refresh()->status)->toBe(PaymentStatus::InProcess);

    $this->post(route('checkout.verify.submit', [$this->session, $payment]), ['code' => '0000']);
    $this->post(route('checkout.verify.submit', [$this->session, $payment]), ['code' => '0000'])
        ->assertRedirect(route('checkout.show', $this->session));

    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed)
        ->and($this->session->refresh()->payment_status)->toBe(CheckoutPaymentStatus::Unpaid);
});

test('a declined card fails after the otp and the session stays payable', function () {
    openCheckout($this, $this->session);
    $this->post(route('checkout.pay', $this->session), card(['card_number' => '4000 0000 0000 0002']));
    $payment = Payment::sole();

    $this->post(route('checkout.verify.submit', [$this->session, $payment]), ['code' => '1234'])
        ->assertRedirect(route('checkout.show', $this->session))
        ->assertSessionHasErrors('payment');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed)
        ->and($this->session->refresh()->isPayable())->toBeTrue();
});

test('3-D Secure cards go through the bank verification step', function () {
    openCheckout($this, $this->session);
    $this->post(route('checkout.pay', $this->session), card(['card_number' => '4456 5300 0000 1096']));
    $payment = Payment::sole();

    $this->get(route('checkout.verify', [$this->session, $payment]))->assertOk()->assertSee('3-D Secure');
    $this->post(route('checkout.verify.submit', [$this->session, $payment]), ['code' => '1234'])->assertRedirect('https://shop.example/success');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Successful);
});

test('another browser cannot open or submit the verification step', function () {
    openCheckout($this, $this->session);
    $this->post(route('checkout.pay', $this->session), card());
    $payment = Payment::sole();

    $this->flushSession();

    $this->get(route('checkout.verify', [$this->session, $payment]))->assertNotFound();
    $this->post(route('checkout.verify.submit', [$this->session, $payment]), ['code' => '1234'])->assertNotFound();

    expect($payment->refresh()->status)->toBe(PaymentStatus::InProcess);
});

test('invalid card details are rejected before reaching the processor', function () {
    openCheckout($this, $this->session);

    $this->post(route('checkout.pay', $this->session), card(['card_number' => '4242 4242 4242 4241', 'expiry' => '01/20', 'cvc' => '1']))
        ->assertSessionHasErrors(['card_number', 'expiry', 'cvc']);

    expect(Payment::count())->toBe(0);
});

test('a paid or expired session shows its status instead of the card form', function () {
    $paid = CheckoutSession::factory()->for($this->merchant)->paid()->create();
    $expired = CheckoutSession::factory()->for($this->merchant)->expired()->create();

    $this->get(route('checkout.show', ['session' => $paid, 'key' => $this->publishableKey]))->assertOk()->assertDontSee('card_number');
    $this->get(route('checkout.show', ['session' => $expired, 'key' => $this->publishableKey]))->assertOk()->assertDontSee('card_number');

    $this->post(route('checkout.pay', $expired), card())->assertSessionHasErrors('payment');
});

test('a paid session cannot be charged a second time', function () {
    openCheckout($this, $this->session);
    $this->post(route('checkout.pay', $this->session), card());
    $this->post(route('checkout.verify.submit', [$this->session, Payment::sole()]), ['code' => '1234']);

    openCheckout($this, $this->session);
    $this->post(route('checkout.pay', $this->session), card())->assertSessionHasErrors('payment');

    expect(Payment::where('status', PaymentStatus::Successful)->count())->toBe(1);
});

test('a starting a new attempt retires the unfinished one', function () {
    openCheckout($this, $this->session);
    $this->post(route('checkout.pay', $this->session), card());
    $this->post(route('checkout.pay', $this->session), card());

    expect(Payment::where('status', PaymentStatus::InProcess)->count())->toBe(1)
        ->and(Payment::where('status', PaymentStatus::Failed)->count())->toBe(1);
});

test('the card is saved for the customer when they ask for it', function () {
    $customer = Customer::factory()->for($this->merchant)->create();
    $session = CheckoutSession::factory()->for($this->merchant)->for($customer)->create();

    openCheckout($this, $session);
    $this->post(route('checkout.pay', $session), card(['save_card' => '1']));
    $this->post(route('checkout.verify.submit', [$session, Payment::sole()]), ['code' => '1234']);

    $card = PaymentMethod::sole();
    expect($card->customer_id)->toBe($customer->id)
        ->and($card->last_four)->toBe('4242')
        ->and($card->processor_token)->toStartWith('tok_sbx_');
});

test('a customer pays with a saved card', function () {
    $customer = Customer::factory()->for($this->merchant)->create();
    $saved = PaymentMethod::factory()->for($customer)->create();
    $session = CheckoutSession::factory()->for($this->merchant)->for($customer)->create();

    openCheckout($this, $session);
    $this->get(route('checkout.show', $session))->assertSee('•••• 4242');

    $this->post(route('checkout.pay-saved', [$session, $saved]));
    $payment = Payment::sole();
    $this->post(route('checkout.verify.submit', [$session, $payment]), ['code' => '1234'])->assertRedirect('https://shop.example/success');

    expect($payment->refresh()->payment_method_id)->toBe($saved->id);
});

test('another customer saved card cannot be used', function () {
    $session = CheckoutSession::factory()->for($this->merchant)->for(Customer::factory()->for($this->merchant))->create();
    $foreignCard = PaymentMethod::factory()->create();

    openCheckout($this, $session);
    $this->post(route('checkout.pay-saved', [$session, $foreignCard]))->assertSessionHasErrors('payment');

    expect(Payment::count())->toBe(0);
});

test('a payer typing with an Arabic keyboard can pay', function () {
    openCheckout($this, $this->session);

    $this->post(route('checkout.pay', $this->session), card([
        'card_number' => '٤٢٤٢ ٤٢٤٢ ٤٢٤٢ ٤٢٤٢',
        'cvc' => '١٢٣',
    ]))->assertRedirect();

    $payment = Payment::sole();
    $this->post(route('checkout.verify.submit', [$this->session, $payment]), ['code' => '١٢٣٤'])
        ->assertRedirect('https://shop.example/success');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Successful);
});

test('the paid page shows a printable receipt and how to reach the merchant', function () {
    openCheckout($this, $this->session);
    $this->post(route('checkout.pay', $this->session), card());
    $this->post(route('checkout.verify.submit', [$this->session, Payment::sole()]), ['code' => '1234']);

    $this->get(route('checkout.show', ['session' => $this->session, 'key' => $this->publishableKey]))
        ->assertOk()
        ->assertSee('data-test="receipt"', false)
        ->assertSee(Payment::sole()->public_id)
        ->assertSee('data-test="merchant-contact"', false)
        ->assertSee($this->merchant->email);
});
