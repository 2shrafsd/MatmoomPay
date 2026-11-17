<?php

use App\Enums\MerchantRole;
use App\Models\CheckoutSession;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->merchant = Merchant::factory()->withMember($this->user, MerchantRole::Owner)->create();
});

test('users without a merchant are sent to onboarding', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding'));
});

test('every portal page renders for the owner', function (string $route) {
    $customer = Customer::factory()->for($this->merchant)->create();
    PaymentMethod::factory()->for($customer)->create();
    $session = CheckoutSession::factory()->for($this->merchant)->for($customer)->create();
    $payment = Payment::factory()->for($this->merchant)->create(['checkout_session_id' => $session->id, 'customer_id' => $customer->id]);
    Refund::factory()->for($payment)->create();
    $link = PaymentLink::factory()->for($this->merchant)->create();
    Subscription::factory()->for(Plan::factory()->for($this->merchant))->create(['merchant_id' => $this->merchant->id, 'customer_id' => $customer->id]);

    $this->actingAs($this->user)->withSession(['auth.password_confirmed_at' => time()]);

    $url = match ($route) {
        'portal.payments.show' => route($route, $payment),
        'portal.customers.show' => route($route, $customer),
        'portal.links.show' => route($route, $link),
        default => route($route),
    };

    $this->get($url)->assertOk();
})->with([
    'dashboard', 'portal.payments', 'portal.payments.show', 'portal.checkouts', 'portal.refunds',
    'portal.customers', 'portal.customers.show', 'portal.links', 'portal.links.show', 'portal.settlements',
    'portal.team', 'portal.developers', 'portal.webhooks', 'portal.business', 'portal.subscriptions', 'portal.plans',
]);
