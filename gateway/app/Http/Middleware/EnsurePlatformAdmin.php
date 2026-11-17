<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the admin panel to platform administrators who have two-factor authentication enabled.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isPlatformAdmin()) {
            abort(404);
        }

        if (config('security.admin_requires_two_factor') && $user->two_factor_confirmed_at === null) {
            return redirect()->route('security.edit')->with('status', 'admin-two-factor-required');
        }

        return $next($request);
    }
}
