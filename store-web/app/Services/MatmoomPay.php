<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A minimal client for the MatmoomPay API, written the way any merchant would integrate.
 */
class MatmoomPay
{
    /**
     * Creates a checkout session for the order and returns its session_id. The customer comes back to the
     * web shop unless other return URLs are given (the mobile app uses its own).
     */
    public function createCheckoutSession(Order $order, ?string $successUrl = null, ?string $cancelUrl = null): string
    {
        $response = $this->api()
            ->withHeaders(['Idempotency-Key' => 'order-'.$order->reference])
            ->post('/checkout/session', [
                'client_reference_id' => $order->reference,
                'mode' => 'payment',
                'products' => array_map(fn (array $item) => [
                    'name' => mb_substr($item['name'], 0, 40),
                    'quantity' => $item['quantity'],
                    'unit_amount' => $item['unit_amount'],
                ], $order->items),
                'success_url' => $successUrl ?? route('orders.return', $order),
                'cancel_url' => $cancelUrl ?? route('cart.show'),
                'metadata' => [
                    'Customer name' => $order->customer_name,
                    'Customer phone' => $order->customer_phone,
                    'order' => $order->reference,
                ],
            ]);

        if (! $response->json('success')) {
            throw new RuntimeException('MatmoomPay refused the session: '.$response->json('description', 'no response'));
        }

        return (string) $response->json('data.session_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveSession(string $sessionId): array
    {
        $response = $this->api()->get('/checkout/session/'.rawurlencode($sessionId));

        if (! $response->json('success')) {
            throw new RuntimeException('Could not read the checkout session.');
        }

        return (array) $response->json('data');
    }

    public function paymentPageUrl(string $sessionId): string
    {
        return config('services.matmoom.pay_url').'/'.rawurlencode($sessionId).'?key='.urlencode((string) config('services.matmoom.publishable_key'));
    }

    /**
     * Checks the webhook signature: HMAC-SHA256 of "{raw body}-{timestamp}" with the signing secret,
     * and refuses events older than five minutes.
     */
    public function webhookIsAuthentic(string $body, string $timestamp, string $signature): bool
    {
        $secret = (string) config('services.matmoom.webhook_secret');

        if ($secret === '' || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $body.'-'.$timestamp, $secret), $signature);
    }

    private function api(): PendingRequest
    {
        return Http::baseUrl((string) config('services.matmoom.base_url'))
            ->withHeaders(['matmoom-api-key' => (string) config('services.matmoom.secret_key')])
            ->acceptJson()
            ->timeout(15);
    }
}
