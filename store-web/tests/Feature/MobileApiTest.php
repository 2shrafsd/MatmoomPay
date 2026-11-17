<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class MobileApiTest extends TestCase
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

    public function test_products_are_listed_with_prices_and_absolute_photo_urls(): void
    {
        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(Product::count(), 'data')
            ->assertJsonPath('data.0', [
                'slug' => 'headphones-beige',
                'name' => 'سماعة رأس لاسلكية هادئة — بيج',
                'description' => 'عزل للضوضاء وبطارية تكفي حتى 60 ساعة، ووسائد ناعمة للاستخدام الطويل.',
                'price' => 285_000,
                'price_formatted' => '285,000 ج.س',
                'image_url' => url('images/products/headphones-beige.jpg'),
                'color' => '#d8cfc2',
                'category' => 'headphones',
            ]);
    }

    public function test_a_product_without_a_photo_file_has_a_null_image_url(): void
    {
        Product::where('slug', 'headphones-beige')->update(['image' => 'images/products/missing.jpg']);

        $this->getJson('/api/products')->assertJsonPath('data.0.image_url', null);
    }

    public function test_placing_an_order_prices_it_from_the_database_and_returns_the_payment_page(): void
    {
        Http::fake(['gateway.test/api/v1/checkout/session' => Http::response(['success' => true, 'code' => 2001, 'data' => ['session_id' => 'checkout_app']])]);

        $response = $this->postJson('/api/orders', [
            'customer_name' => 'سالم',
            'customer_phone' => '+249 91 234 5678',
            'items' => [['slug' => 'headphones-anc-black', 'quantity' => 2], ['slug' => 'usb-c-cable', 'quantity' => 1]],
        ]);

        $order = Order::sole();
        $token = $response->json('data.access_token');

        $response->assertCreated()->assertExactJson(['data' => [
            'reference' => $order->reference,
            'status' => 'pending',
            'total' => 689_500,
            'total_formatted' => '689,500 ج.س',
            'payment_url' => 'https://gateway.test/pay/checkout_app?key=pk_test_example',
            'access_token' => $token,
            'success_url_prefix' => url('/m/orders/'.$order->reference.'/return'),
            'cancel_url_prefix' => url('/m/orders/'.$order->reference.'/cancel'),
        ]]);

        $this->assertMatchesRegularExpression('/^MJ-[A-Z0-9]{8}$/', $order->reference);
        $this->assertSame(40, strlen($token));
        $this->assertSame(hash('sha256', $token), $order->access_token_hash);
        $this->assertSame('checkout_app', $order->session_id);

        Http::assertSent(fn (Request $request) => $request->hasHeader('matmoom-api-key', 'sk_test_example')
            && $request['products'][0]['unit_amount'] === 340_000
            && $request['products'][0]['quantity'] === 2
            && $request['success_url'] === route('mobile.return', $order)
            && $request['cancel_url'] === route('mobile.cancel', $order));
    }

    public function test_an_invalid_order_is_rejected_with_validation_errors(): void
    {
        Http::fake();

        $this->postJson('/api/orders', [
            'customer_name' => str_repeat('a', 81),
            'customer_phone' => 'not a phone',
            'items' => [['slug' => 'does-not-exist', 'quantity' => 11]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_name', 'customer_phone', 'items.0.slug', 'items.0.quantity']);

        $this->postJson('/api/orders', ['customer_name' => 'سالم', 'customer_phone' => '+249912345678', 'items' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        $this->assertSame(0, Order::count());
        Http::assertNothingSent();
    }

    public function test_a_gateway_failure_returns_502_and_marks_the_order_failed(): void
    {
        Sleep::fake();
        Http::fake(['gateway.test/*' => Http::response('down', 500)]);

        $this->postJson('/api/orders', [
            'customer_name' => 'سالم',
            'customer_phone' => '+249912345678',
            'items' => [['slug' => 'usb-c-cable', 'quantity' => 1]],
        ])
            ->assertStatus(502)
            ->assertJsonPath('message', 'تعذر الاتصال ببوابة الدفع. حاول مرة أخرى بعد قليل.');

        $this->assertSame('failed', Order::sole()->status);
    }

    public function test_an_order_can_only_be_read_with_its_token(): void
    {
        $order = $this->appOrder();
        $token = $order->issueAccessToken();

        $this->getJson('/api/orders/'.$order->reference)->assertNotFound();
        $this->getJson('/api/orders/'.$order->reference, ['X-Order-Token' => 'wrong-token'])->assertNotFound();

        $this->getJson('/api/orders/'.$order->reference, ['X-Order-Token' => $token])
            ->assertOk()
            ->assertJsonPath('data.reference', $order->reference)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total', 9_500)
            ->assertJsonPath('data.total_formatted', '9,500 ج.س')
            ->assertJsonPath('data.items.0', ['name' => 'كابل شحن USB-C مضفّر، 1 متر', 'quantity' => 1, 'unit_amount' => 9_500, 'image_url' => url('images/products/usb-c-cable.jpg')])
            ->assertJsonPath('data.invoice', null)
            ->assertJsonPath('data.paid_at', null)
            ->assertJsonPath('data.created_at', $order->created_at->toIso8601String())
            ->assertJsonMissingPath('data.customer_phone');
    }

    public function test_reading_an_order_confirms_the_payment_with_the_gateway(): void
    {
        $order = $this->appOrder(['session_id' => 'checkout_paid']);
        $token = $order->issueAccessToken();

        Http::fake(['gateway.test/api/v1/checkout/session/checkout_paid' => Http::sequence()
            ->push(['success' => true, 'data' => ['payment_status' => 'paid', 'total_amount' => 1_000, 'invoice' => '111']])
            ->push(['success' => true, 'data' => ['payment_status' => 'paid', 'total_amount' => 9_500, 'invoice' => '2611171']])]);

        // A paid session for a different amount does not pay the order.
        $this->getJson('/api/orders/'.$order->reference, ['X-Order-Token' => $token])->assertJsonPath('data.status', 'pending');

        $this->getJson('/api/orders/'.$order->reference, ['X-Order-Token' => $token])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.invoice', '2611171')
            ->assertJsonPath('data.paid_at', $order->refresh()->paid_at->toIso8601String());
    }

    public function test_the_mobile_return_pages_exist_and_cancel_marks_a_pending_order_cancelled(): void
    {
        $order = $this->appOrder();

        $this->get(route('mobile.return', $order))->assertOk()->assertSee('تم، ارجع إلى التطبيق');
        $this->assertSame('pending', $order->refresh()->status);

        $this->get(route('mobile.cancel', $order))->assertOk()->assertSee('أُلغي الدفع');
        $this->assertSame('cancelled', $order->refresh()->status);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function appOrder(array $attributes = []): Order
    {
        $cable = Product::where('slug', 'usb-c-cable')->firstOrFail();

        return Order::create([
            'reference' => 'MJ-APP00001',
            'customer_name' => 'نور',
            'customer_phone' => '+249912345678',
            'items' => [['product_id' => $cable->id, 'name' => $cable->name, 'quantity' => 1, 'unit_amount' => $cable->price]],
            'total' => $cable->price,
            ...$attributes,
        ]);
    }
}
