<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\MatmoomPay;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives signed events from MatmoomPay. Handling is idempotent: the same event can arrive twice.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request, MatmoomPay $matmoom): Response
    {
        $authentic = $matmoom->webhookIsAuthentic(
            $request->getContent(),
            (string) $request->header('matmoom-timestamp'),
            (string) $request->header('matmoom-signature'),
        );

        if (! $authentic) {
            return response('invalid signature', 400);
        }

        $event = (string) $request->json('event_type');
        $data = (array) $request->json('data');

        if ($event === 'checkout.completed' && ($data['payment_status'] ?? null) === 'paid') {
            Order::where('reference', $data['client_reference_id'] ?? '')
                ->where('session_id', $data['session_id'] ?? '')
                ->where('total', (int) ($data['total_amount'] ?? -1))
                ->where('status', '!=', 'paid')
                ->update(['status' => 'paid', 'invoice' => $data['invoice'] ?? null, 'paid_at' => now()]);
        }

        if ($event === 'checkout.cancelled') {
            Order::where('session_id', $data['session_id'] ?? '')->where('status', 'pending')->update(['status' => 'cancelled']);
        }

        // The customer never paid and the session ran out: the order stops waiting.
        if ($event === 'checkout.expired') {
            Order::where('session_id', $data['session_id'] ?? '')->whereIn('status', ['pending', 'abandoned'])->update(['status' => 'expired']);
        }

        return response('ok');
    }
}
