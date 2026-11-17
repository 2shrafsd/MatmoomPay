<?php

use App\Actions\Subscriptions\RecordInvoiceOutcome;
use App\Actions\Subscriptions\RenewSubscription;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Jobs\RenewSubscriptionJob;
use App\Models\ApiKey;
use App\Models\CheckoutSession;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Database\Factories\ApiKeyFactory;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Queue::fake();
    [$this->key, $this->headers] = apiKeyFor();
    $this->merchant = $this->key->merchant;
    $this->plan = Plan::factory()->for($this->merchant)->create(['amount' => 15_000]);
    $this->customer = Customer::factory()->for($this->merchant)->create();
});

function dueSubscription($test, string $card = '4242424242424242'): Subscription
{
    return Subscription::factory()->for($test->plan)->create([
        'merchant_id' => $test->merchant->id,
        'customer_id' => $test->customer->id,
        'payment_method_id' => PaymentMethod::factory()->for($test->customer)->card($card)->create()->id,
    ]);
}

test('a subscription session needs an active plan of the merchant and a customer', function () {
    $foreignPlan = Plan::factory()->create();
    $payload = ['client_reference_id' => 'sub-1', 'mode' => 'subscription', 'success_url' => 'https://shop.example/ok', 'cancel_url' => 'https://shop.example/no', 'metadata' => []];

    $this->postJson('/api/v1/checkout/session', $payload + ['customer_id' => $this->customer->public_id], $this->headers)
        ->assertStatus(400)->assertJsonFragment(['field' => 'plan_id']);

    $this->postJson('/api/v1/checkout/session', $payload + ['plan_id' => $this->plan->public_id], $this->headers)
        ->assertStatus(400)->assertJsonFragment(['field' => 'customer_id']);

    $this->postJson('/api/v1/checkout/session', $payload + ['plan_id' => $foreignPlan->public_id, 'customer_id' => $this->customer->public_id], $this->headers)
        ->assertStatus(400)->assertJsonFragment(['field' => 'plan_id']);

    $this->postJson('/api/v1/checkout/session', $payload + ['plan_id' => $this->plan->public_id, 'customer_id' => $this->customer->public_id], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.mode', 'subscription')
        ->assertJsonPath('data.total_amount', 15_000)
        ->assertJsonPath('data.plan_id', $this->plan->public_id);
});

test('paying a subscription checkout saves the card and starts the subscription', function () {
    WebhookEndpoint::factory()->for($this->merchant)->create();
    ApiKey::factory()->for($this->merchant)->publishable()->create();
    $session = CheckoutSession::factory()->for($this->merchant)->for($this->customer)->create([
        'mode' => 'subscription', 'plan_id' => $this->plan->id, 'total_amount' => 15_000,
        'products' => [['name' => $this->plan->name, 'quantity' => 1, 'unit_amount' => 15_000]],
    ]);

    $this->get(route('checkout.show', ['session' => $session, 'key' => ApiKeyFactory::$lastToken]))
        ->assertOk()
        ->assertSee('data-test="recurring-consent"', false);

    $this->post(route('checkout.pay', $session), ['card_number' => '4242424242424242', 'expiry' => now()->addYear()->format('m/y'), 'cvc' => '123', 'cardholder_name' => 'Aisha']);
    $this->post(route('checkout.verify.submit', [$session, Payment::sole()]), ['code' => '1234'])->assertRedirect('https://shop.example/success');

    $subscription = Subscription::sole();
    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->payment_method_id)->toBe(PaymentMethod::sole()->id)
        ->and($subscription->current_period_end->isFuture())->toBeTrue()
        ->and($subscription->invoices()->sole()->status)->toBe(InvoiceStatus::Paid)
        ->and(WebhookDelivery::pluck('event_type')->all())->toContain('subscription.created', 'invoice.paid');
});

test('a due subscription renews off-session and moves to the next period', function () {
    $subscription = dueSubscription($this);
    $previousEnd = $subscription->current_period_end;

    app(RenewSubscription::class)->handle($subscription);

    $subscription->refresh();
    $invoice = $subscription->invoices()->sole();

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->amount_paid)->toBe(15_000)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->current_period_start->equalTo($previousEnd))->toBeTrue()
        ->and($subscription->current_period_end->greaterThan($previousEnd->addWeeks(3)))->toBeTrue()
        ->and(Payment::sole()->amount)->toBe(15_000);
});

test('running the renewal twice never charges the same period twice', function () {
    $subscription = dueSubscription($this);

    app(RenewSubscription::class)->handle($subscription);
    app(RenewSubscription::class)->handle($subscription->refresh());

    expect(Payment::count())->toBe(1)->and($subscription->invoices()->count())->toBe(1);
});

test('a declined renewal makes the subscription past due and retries later', function () {
    $subscription = dueSubscription($this, '4000000000000002');

    app(RenewSubscription::class)->handle($subscription);

    $invoice = $subscription->invoices()->sole();
    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::PastDue)
        ->and($invoice->status)->toBe(InvoiceStatus::Open)
        ->and($invoice->attempt_count)->toBe(1)
        ->and($invoice->next_payment_attempt->isFuture())->toBeTrue();

    app(RenewSubscription::class)->handle($subscription->refresh());
    expect($invoice->refresh()->attempt_count)->toBe(1);
});

test('the subscription is cancelled after the last failed attempt', function () {
    $subscription = dueSubscription($this, '4000000000000002');

    foreach (range(1, RecordInvoiceOutcome::MAX_ATTEMPTS) as $attempt) {
        $this->travelTo(now()->addDays(3));
        app(RenewSubscription::class)->handle($subscription->refresh());
    }

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($subscription->invoices()->sole()->status)->toBe(InvoiceStatus::Void);
});

test('a renewal that needs 3-D Secure waits with a next action link', function () {
    $subscription = dueSubscription($this, '4456530000001096');

    app(RenewSubscription::class)->handle($subscription);

    $invoice = $subscription->invoices()->with('paymentIntent')->sole();
    expect($invoice->paymentIntent->status->value)->toBe('requires_action')
        ->and($invoice->paymentIntent->next_action['url'])->toContain('/authenticate/');

    $payment = Payment::sole();
    $this->post(URL::temporarySignedRoute('authenticate.submit', now()->addMinutes(5), ['payment' => $payment->public_id]), ['code' => '1234'])
        ->assertRedirect(route('subscriptions.authenticated'));

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($subscription->refresh()->status)->toBe(SubscriptionStatus::Active);
});

test('the final failed renewal cancels its 3-D Secure intent so the cancelled subscription cannot be charged', function () {
    $subscription = dueSubscription($this, '4456530000001096');
    SubscriptionInvoice::create([
        'merchant_id' => $subscription->merchant_id,
        'livemode' => false,
        'subscription_id' => $subscription->id,
        'amount_due' => 15_000,
        'currency' => $this->plan->currency,
        'status' => InvoiceStatus::Open,
        'attempt_count' => RecordInvoiceOutcome::MAX_ATTEMPTS - 1,
        'next_payment_attempt' => now()->subMinute(),
        'period_start' => $subscription->current_period_end,
        'period_end' => $subscription->current_period_end->addMonth(),
    ]);

    app(RenewSubscription::class)->handle($subscription);

    $invoice = $subscription->invoices()->with('paymentIntent')->sole();
    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($invoice->status)->toBe(InvoiceStatus::Void)
        ->and($invoice->paymentIntent->status->value)->toBe('cancelled')
        ->and(Payment::sole()->status)->toBe(PaymentStatus::Failed);

    $this->post(URL::temporarySignedRoute('authenticate.submit', now()->addMinutes(5), ['payment' => Payment::sole()->public_id]), ['code' => '1234']);

    expect(Payment::sole()->status)->toBe(PaymentStatus::Failed);
});

test('cancel at period end stops the next renewal', function () {
    $subscription = dueSubscription($this);

    $this->postJson("/api/v1/subscriptions/{$subscription->public_id}/cancel", ['at_period_end' => true], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.cancel_at_period_end', true);

    app(RenewSubscription::class)->handle($subscription->refresh());

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Cancelled)->and(Payment::count())->toBe(0);
});

test('subscriptions and plans of other merchants are invisible', function () {
    $foreign = Subscription::factory()->create();

    $this->getJson("/api/v1/subscriptions/{$foreign->public_id}", $this->headers)->assertNotFound();
    $this->postJson("/api/v1/subscriptions/{$foreign->public_id}/cancel", [], $this->headers)->assertNotFound();
    $this->getJson("/api/v1/plans/{$foreign->plan->public_id}", $this->headers)->assertNotFound();
});

test('plans are created through the api', function () {
    $this->postJson('/api/v1/plans', ['name' => 'Yearly licence', 'amount' => 120_000, 'interval' => 'year'], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.interval', 'year')
        ->assertJsonPath('data.amount', 120_000);

    $this->postJson('/api/v1/plans', ['name' => 'Bad', 'amount' => 10, 'interval' => 'decade'], $this->headers)
        ->assertStatus(400)
        ->assertJsonFragment(['field' => 'interval'])
        ->assertJsonFragment(['field' => 'amount']);
});

test('the billing command queues only due subscriptions', function () {
    dueSubscription($this);
    Subscription::factory()->for($this->plan)->create(['merchant_id' => $this->merchant->id, 'current_period_end' => now()->addWeek()]);

    $this->artisan('gateway:bill-subscriptions')->expectsOutput('Queued 1 subscription renewals.')->assertSuccessful();

    Queue::assertPushed(RenewSubscriptionJob::class, 1);
});
