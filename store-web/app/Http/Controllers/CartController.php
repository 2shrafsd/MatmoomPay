<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The cart lives in the session as [product_id => quantity]. Prices always come from the database.
 */
class CartController extends Controller
{
    public function show(Request $request): View
    {
        return view('shop.cart', self::summary($request));
    }

    public function add(Request $request, Product $product): RedirectResponse
    {
        $cart = $request->session()->get('cart', []);
        $cart[$product->id] = min(10, ($cart[$product->id] ?? 0) + 1);
        $request->session()->put('cart', $cart);

        return back()->with('status', 'أُضيف '.$product->name.' إلى السلة.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $quantity = (int) $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:10']])['quantity'];
        $cart = $request->session()->get('cart', []);

        if ($quantity === 0) {
            unset($cart[$product->id]);
        } else {
            $cart[$product->id] = $quantity;
        }

        $request->session()->put('cart', $cart);

        return redirect()->route('cart.show');
    }

    /**
     * @return array{lines: list<array{product: Product, quantity: int, subtotal: int}>, total: int}
     */
    public static function summary(Request $request): array
    {
        $cart = $request->session()->get('cart', []);
        $products = Product::whereKey(array_keys($cart))->get()->keyBy('id');
        $lines = [];

        foreach ($cart as $productId => $quantity) {
            if ($product = $products->get($productId)) {
                $lines[] = ['product' => $product, 'quantity' => (int) $quantity, 'subtotal' => $product->price * (int) $quantity];
            }
        }

        return ['lines' => $lines, 'total' => array_sum(array_column($lines, 'subtotal'))];
    }
}
