<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Middleware\EnsureMerchantAccount;
use App\Models\Merchant;
use App\Support\GatewayAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PortalContextController
{
    /**
     * Switches the dashboard between test and live data. Live mode requires an approved merchant.
     */
    public function mode(Request $request, GatewayAccount $account): RedirectResponse
    {
        $live = $request->boolean('live');

        if ($live && ! $account->merchant->status->canProcessLivePayments()) {
            return back()->with('status', 'live-mode-unavailable');
        }

        $request->session()->put(EnsureMerchantAccount::MODE_SESSION_KEY, $live);

        return back();
    }

    public function merchant(Request $request, Merchant $merchant): RedirectResponse
    {
        if (! $request->user()?->switchMerchant($merchant)) {
            abort(404);
        }

        $request->session()->put(EnsureMerchantAccount::MODE_SESSION_KEY, false);

        return redirect()->route('dashboard');
    }
}
