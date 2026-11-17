<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\MatmoomPay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function store(Request $request, MatmoomPay $matmoom): RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:80'],
            'customer_phone' => ['required', 'string', 'regex:'.Order::PHONE_PATTERN],
        ]);

        $summary = CartController::summary($request);

        if ($summary['lines'] === []) {
            return redirect()->route('shop.index');
        }

        // A shopper who went back to the cart and checks out again abandons the earlier attempt.
        Order::where('reference', $request->session()->get('pending_order'))->where('status', 'pending')->update(['status' => 'abandoned']);

        $order = Order::place($validated['customer_name'], $validated['customer_phone'], $summary['lines']);

        $order->rememberInThisBrowser();

        try {
            // Retrying with the same Idempotency-Key is safe: if the first call created the session but the
            // answer was lost, the gateway replays the original response instead of creating a second one.
            $sessionId = retry(2, fn () => $matmoom->createCheckoutSession($order), 500);
        } catch (\Throwable $exception) {
            Log::error('Checkout session failed', ['order' => $order->reference, 'error' => $exception->getMessage()]);
            $order->update(['status' => 'failed']);

            return back()->withErrors(['checkout' => 'تعذر الاتصال ببوابة الدفع. تأكد أن MatmoomPay يعمل على المنفذ 8000 وأن المفاتيح صحيحة.']);
        }

        $order->update(['session_id' => $sessionId]);

        return redirect()->away($matmoom->paymentPageUrl($sessionId));
    }
}
