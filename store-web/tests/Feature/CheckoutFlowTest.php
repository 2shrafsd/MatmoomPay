<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        config([
            'services.matmoom.base_url' => 'https://gateway.test/api/v1',
            'services.matmoom.pay_url' => 'https://gateway.test/pay',
            'services.matmoom.secret_key' => 'sk_test_example',
            'services.matmoom.publishable_key' => 'pk_test_example',
            'services.matmoom.webhook_secret' => 'whsec_example',
        ]);
    }

    public function test_checkout_creates_a_session_with_database_prices_and_redirects_to_the_gateway(): void
    {
        Http::fake(['gateway.test/api/v1/checkout/session' => Http::response(['success' => true, 'code' => 2001, 'data' => ['session_id' => 'checkout_abc']])]);
        $headphones = Product::where('slug', 'headphones-anc-black')->firstOrFail();

        $this->post(route('cart.add', $headphones));
        $this->post(route('cart.add', $headphones));

        $this->post(route('checkout'), ['customer_name' => 'سالم', 'customer_phone' => '+249 91 234 5678'])
            ->assertRedirect('https://gateway.test/pay/checkout_abc?key=pk_test_example');

        Http::assertSent(fn (Request $request) => $request->hasHeader('matmoom-api-key', 'sk_test_example')
            && $request['products'][0]['unit_amount'] === 340_000
            && $request['products'][0]['quantity'] === 2);

        $this->assertSame('checkout_abc', Order::sole()->session_id);
    }

    public function test_an_order_is_marked_paid_only_when_the_gateway_confirms_it(): void
    {
        $order = Order::create(['reference' => 'MJ-TEST1', 'customer_name' => 'نور', 'customer_phone' => '+249912345678', 'items' => [], 'total' => 4_200, 'session_id' => 'checkout_x']);

        Http::fake(['gateway.test/api/v1/checkout/session/checkout_x' => Http::sequence()
            ->push(['success' => true, 'data' => ['payment_status' => 'unpaid', 'total_amount' => 4_200]])
            ->push(['success' => true, 'data' => ['payment_status' => 'paid', 'total_amount' => 4_200, 'invoice' => '2610201']])]);

        $this->withSession(['placed_orders' => ['MJ-TEST1']]);

        $this->get(route('orders.return', $order))->assertRedirect(route('orders.show', $order));
        $this->assertSame('pending', $order->refresh()->status);

        $this->get(route('orders.return', $order));
        $this->assertSame('paid', $order->refresh()->status);
    }

    public function test_webhooks_with_a_wrong_signature_are_rejected(): void
    {
        $order = Order::create(['reference' => 'MJ-TEST2', 'customer_name' => 'خالد', 'customer_phone' => '+249912345678', 'items' => [], 'total' => 3_200, 'session_id' => 'checkout_y']);
        $body = json_encode(['event_type' => 'checkout.completed', 'data' => ['client_reference_id' => 'MJ-TEST2', 'session_id' => 'checkout_y', 'payment_status' => 'paid', 'total_amount' => 3_200]]);
        $timestamp = (string) time();

        $this->call('POST', route('webhooks.matmoom'), [], [], [], ['HTTP_MATMOOM_TIMESTAMP' => $timestamp, 'HTTP_MATMOOM_SIGNATURE' => 'forged', 'CONTENT_TYPE' => 'application/json'], $body)->assertStatus(400);
        $this->assertSame('pending', $order->refresh()->status);

        $signature = hash_hmac('sha256', $body.'-'.$timestamp, 'whsec_example');
        $this->call('POST', route('webhooks.matmoom'), [], [], [], ['HTTP_MATMOOM_TIMESTAMP' => $timestamp, 'HTTP_MATMOOM_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $body)->assertOk();
        $this->assertSame('paid', $order->refresh()->status);
    }

    public function test_an_order_page_only_opens_in_the_browser_that_placed_it(): void
    {
        $order = Order::create(['reference' => 'MJ-PRIV1', 'customer_name' => 'أمنة', 'customer_phone' => '+249912345678', 'items' => [], 'total' => 4_200]);

        $this->get(route('orders.show', $order))->assertNotFound();
        $this->get(route('orders.index'))->assertDontSee('MJ-PRIV1');

        $this->withSession(['placed_orders' => ['MJ-PRIV1']])->get(route('orders.show', $order))->assertOk()->assertSee('MJ-PRIV1');
    }

    public function test_the_cart_empties_when_the_webhook_confirmed_the_payment_first(): void
    {
        $order = Order::create(['reference' => 'MJ-HOOK1', 'customer_name' => 'نور', 'customer_phone' => '+249912345678', 'items' => [], 'total' => 4_200, 'session_id' => 'checkout_h', 'status' => 'paid']);

        $this->withSession(['placed_orders' => ['MJ-HOOK1'], 'cart' => [1 => 2]])->get(route('orders.return', $order));

        $this->assertNull(session('cart'));
    }

    public function test_a_gateway_error_on_return_shows_a_notice_instead_of_an_error_page(): void
    {
        Http::fake(['gateway.test/*' => Http::response('down', 500)]);
        $order = Order::create(['reference' => 'MJ-DOWN1', 'customer_name' => 'نور', 'customer_phone' => '+249912345678', 'items' => [], 'total' => 4_200, 'session_id' => 'checkout_d']);

        $this->withSession(['placed_orders' => ['MJ-DOWN1']])->get(route('orders.return', $order))
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('notice');
    }

    public function test_an_expired_session_closes_the_order(): void
    {
        $order = Order::create(['reference' => 'MJ-EXP1', 'customer_name' => 'خالد', 'customer_phone' => '+249912345678', 'items' => [], 'total' => 3_200, 'session_id' => 'checkout_e']);
        $body = json_encode(['id' => 'evt_1', 'event_type' => 'checkout.expired', 'data' => ['session_id' => 'checkout_e', 'payment_status' => 'expired']]);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $body.'-'.$timestamp, 'whsec_example');

        $this->call('POST', route('webhooks.matmoom'), [], [], [], ['HTTP_MATMOOM_TIMESTAMP' => $timestamp, 'HTTP_MATMOOM_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $body)->assertOk();

        $this->assertSame('expired', $order->refresh()->status);
    }

    public function test_a_lost_answer_is_retried_with_the_same_idempotency_key(): void
    {
        Http::fake(['gateway.test/api/v1/checkout/session' => Http::sequence()
            ->pushStatus(504)
            ->push(['success' => true, 'code' => 2001, 'data' => ['session_id' => 'checkout_retry']])]);
        $this->post(route('cart.add', Product::firstOrFail()));

        $this->post(route('checkout'), ['customer_name' => 'سالم', 'customer_phone' => '+249 91 234 5678'])->assertRedirect();

        $this->assertSame('checkout_retry', Order::sole()->session_id);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request->header('Idempotency-Key') === ['order-'.Order::sole()->reference]);
    }
}
