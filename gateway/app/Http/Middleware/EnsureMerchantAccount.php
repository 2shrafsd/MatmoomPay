<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\MerchantStatus;
use App\Models\Merchant;
use App\Support\GatewayAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the merchant and mode for dashboard pages. Users without a merchant go to onboarding
 * (platform admins go to the admin panel instead); members of a banned merchant are shut out;
 * live mode is only available once the merchant is approved.
 */
class EnsureMerchantAccount
{
    public const MODE_SESSION_KEY = 'portal.livemode';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $merchant = $user?->currentMerchant;

        if ($merchant === null || $merchant->status->blocksPortal() || $user->roleIn($merchant) === null) {
            $merchant = $user?->merchants()->where('merchants.status', '!=', MerchantStatus::Banned)->oldest('merchant_user.id')->first();

            if ($merchant === null) {
                if (! $user->isPlatformAdmin() && $user->merchants()->exists()) {
                    abort(403, __('This business account has been banned. Contact support if you think this is a mistake.'));
                }

                return redirect()->route($user->isPlatformAdmin() ? 'admin.dashboard' : 'onboarding');
            }

            $user->switchMerchant($merchant);
        }

        app()->instance(GatewayAccount::class, new GatewayAccount($merchant, $this->livemode($request, $merchant)));

        return $next($request);
    }

    private function livemode(Request $request, Merchant $merchant): bool
    {
        $wantsLive = (bool) $request->session()->get(self::MODE_SESSION_KEY, false);

        if ($wantsLive && ! $merchant->status->canProcessLivePayments()) {
            $request->session()->put(self::MODE_SESSION_KEY, false);

            return false;
        }

        return $wantsLive;
    }
}
