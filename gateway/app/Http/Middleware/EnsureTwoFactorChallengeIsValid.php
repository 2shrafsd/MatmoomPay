<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A pending two-factor login is only valid while the account still has two-factor enabled.
 * If it was turned off (or reset) after the password step, the login starts over instead of
 * Fortify trying to decrypt a secret that no longer exists.
 */
class EnsureTwoFactorChallengeIsValid
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('two-factor.login', 'two-factor.login.store') || ! $request->session()->has('login.id')) {
            return $next($request);
        }

        $user = User::query()->whereKey((int) $request->session()->get('login.id'))->first();

        if ($user === null || $user->two_factor_secret === null || $user->two_factor_confirmed_at === null) {
            $request->session()->forget(['login.id', 'login.remember']);

            return redirect()->route('login')->with('status', __('Your sign-in expired. Please sign in again.'));
        }

        return $next($request);
    }
}
