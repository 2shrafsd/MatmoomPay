<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\View\View;

/**
 * Where the payment page sends customers who paid from the mobile app. The app's WebView catches these
 * URLs and closes itself; the pages only matter if someone opens them in a browser.
 */
class MobileReturnController extends Controller
{
    public function return(Order $order): View
    {
        return view('shop.mobile-return', ['heading' => 'تم، ارجع إلى التطبيق', 'message' => 'سيعرض التطبيق حالة طلبك.']);
    }

    public function cancel(Order $order): View
    {
        if ($order->status === 'pending') {
            $order->update(['status' => 'cancelled']);
        }

        return view('shop.mobile-return', ['heading' => 'أُلغي الدفع', 'message' => 'لم يُخصم أي مبلغ. يمكنك الرجوع إلى التطبيق.']);
    }
}
