<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Actions\Team\AcceptTeamInvitation;
use App\Http\Middleware\EnsureMerchantAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvitationController
{
    public function __invoke(Request $request, string $token, AcceptTeamInvitation $acceptInvitation): RedirectResponse
    {
        $merchant = $acceptInvitation->handle($request->user(), $token);

        if ($merchant === null) {
            return redirect()->route('dashboard')->with('status', 'invitation-invalid');
        }

        $request->session()->put(EnsureMerchantAccount::MODE_SESSION_KEY, false);

        return redirect()->route('dashboard')->with('status', 'invitation-accepted');
    }
}
