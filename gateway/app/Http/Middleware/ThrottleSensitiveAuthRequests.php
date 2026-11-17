<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ClientAddress;
use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limits the Fortify routes that send email or check the account password, which Fortify leaves unthrottled:
 * sign-up and password-reset requests (mail to any address) and password confirmation (the step-up guess).
 */
class ThrottleSensitiveAuthRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST')) {
            return $next($request);
        }

        $limits = match (true) {
            $request->routeIs('register.store') => [
                ['register:'.ClientAddress::key($request), 5, 60],
                ['register-hour:'.ClientAddress::key($request), 20, 3600],
            ],
            $request->routeIs('password.email') => [
                ['reset:'.ClientAddress::key($request), 5, 60],
                ['reset-to:'.sha1(Str::lower((string) $request->input('email'))), 3, 3600],
            ],
            $request->routeIs('password.confirm.store') => [
                ['confirm:'.($request->user()?->getAuthIdentifier() ?? ClientAddress::key($request)), 5, 60],
            ],
            default => [],
        };

        foreach ($limits as [$key, $maxAttempts, $decaySeconds]) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                throw new ThrottleRequestsException(__('You tried this too many times in a short while. Wait a minute, then try again.'));
            }
        }

        foreach ($limits as [$key, , $decaySeconds]) {
            RateLimiter::hit($key, $decaySeconds);
        }

        return $next($request);
    }
}
