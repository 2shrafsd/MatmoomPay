<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Services\MatmoomPay;
use App\Support\Sdg;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Orders placed from the mobile app. The app never sees the gateway's secret key: this server creates the
 * checkout session, and the app opens the hosted payment page it gets back.
 */
class OrderController extends Controller
{
    public function store(Request $request, MatmoomPay $matmoom): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:80'],
            'customer_phone' => ['required', 'string', 'regex:'.Order::PHONE_PATTERN],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.slug' => ['required', 'string', 'exists:products,slug'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $order = Order::place($validated['customer_name'], $validated['customer_phone'], $this->linesFor($validated['items']));
        $accessToken = $order->issueAccessToken();

        try {
            // Same Idempotency-Key on every attempt, so a retried call never opens a second session.
            $sessionId = retry(2, fn () => $matmoom->createCheckoutSession(
                $order,
                route('mobile.return', $order),
                route('mobile.cancel', $order),
            ), 500);
        } catch (\Throwable $exception) {
            Log::error('Mobile checkout session failed', ['order' => $order->reference, 'error' => $exception->getMessage()]);
            $order->update(['status' => 'failed']);

            return response()->json(['message' => 'تعذر الاتصال ببوابة الدفع. حاول مرة أخرى بعد قليل.'], 502);
        }

        $order->update(['session_id' => $sessionId]);

        return response()->json(['data' => [
            'reference' => $order->reference,
            'status' => $order->status,
            'total' => $order->total,
            'total_formatted' => Sdg::format($order->total),
            'payment_url' => $matmoom->paymentPageUrl($sessionId),
            'access_token' => $accessToken,
            'success_url_prefix' => url('/m/orders/'.$order->reference.'/return'),
            'cancel_url_prefix' => url('/m/orders/'.$order->reference.'/cancel'),
        ]], 201);
    }

    /**
     * Only the app that placed the order holds its token; anyone else gets a 404, as if it did not exist.
     */
    public function show(Request $request, Order $order, MatmoomPay $matmoom): OrderResource
    {
        abort_unless($order->acceptsAccessToken($request->header('X-Order-Token')), 404);

        if (! $order->isPaid() && $order->session_id) {
            try {
                $order->applyGatewaySession($matmoom->retrieveSession($order->session_id));
            } catch (\Throwable $exception) {
                Log::warning('Could not confirm the payment for the app', ['order' => $order->reference, 'error' => $exception->getMessage()]);
            }
        }

        return new OrderResource($order->refresh());
    }

    /**
     * Turns the requested items into order lines priced from the database. A product listed twice is merged.
     *
     * @param  list<array{slug: string, quantity: int}>  $items
     * @return list<array{product: Product, quantity: int, subtotal: int}>
     */
    private function linesFor(array $items): array
    {
        $quantities = [];

        foreach ($items as $item) {
            $quantities[$item['slug']] = ($quantities[$item['slug']] ?? 0) + (int) $item['quantity'];
        }

        $products = Product::whereIn('slug', array_keys($quantities))->get()->keyBy('slug');

        return array_map(fn (string $slug) => [
            'product' => $products[$slug],
            'quantity' => $quantities[$slug],
            'subtotal' => $products[$slug]->price * $quantities[$slug],
        ], array_keys($quantities));
    }
}
