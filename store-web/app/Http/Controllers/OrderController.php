<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\MatmoomPay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * The customer comes back from the payment page. We never trust the redirect itself:
     * the session status is read from the gateway with our secret key.
     */
    public function return(Request $request, Order $order, MatmoomPay $matmoom): RedirectResponse
    {
        abort_unless($order->belongsToThisBrowser(), 404);

        if (! $order->isPaid() && $order->session_id) {
            try {
                $session = $matmoom->retrieveSession($order->session_id);
            } catch (\Throwable $exception) {
                Log::warning('Could not confirm the payment on return', ['order' => $order->reference, 'error' => $exception->getMessage()]);

                return redirect()->route('orders.show', $order)->with('notice', 'نتحقق من دفعتك الآن. حدّث الصفحة بعد لحظات.');
            }

            $order->applyGatewaySession($session);
        }

        // The webhook may have marked the order paid before the customer came back; the cart empties either way.
        if ($order->refresh()->isPaid()) {
            $request->session()->forget(['cart', 'pending_order']);
        }

        return redirect()->route('orders.show', $order);
    }

    public function show(Order $order): View
    {
        abort_unless($order->belongsToThisBrowser(), 404);

        return view('shop.order', [
            'order' => $order,
            'products' => Product::whereKey(array_column($order->items, 'product_id'))->get()->keyBy('id'),
        ]);
    }

    public function index(): View
    {
        return view('shop.orders', ['orders' => Order::whereIn('reference', Order::placedInSession())->latest()->limit(30)->get()]);
    }
}
