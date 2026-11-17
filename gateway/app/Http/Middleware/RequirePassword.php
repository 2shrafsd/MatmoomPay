<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RequirePassword as BaseRequirePassword;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Password confirmation that fails closed for JSON requests.
 *
 * Laravel answers an expired confirmation on a JSON request with a returned 423 response. Livewire's
 * persistent middleware only stops an update on a redirect or an exception, so a returned response
 * would let the component action run. Throwing keeps the step-up enforced on every Livewire update.
 */
class RequirePassword extends BaseRequirePassword
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle($request, Closure $next, $redirectToRoute = null, $passwordTimeoutSeconds = null): mixed
    {
        // Route middleware parameters arrive as strings, e.g. 'password.confirm:,300'.
        $timeout = $passwordTimeoutSeconds === null || $passwordTimeoutSeconds === '' ? null : (int) $passwordTimeoutSeconds;

        if ($this->shouldConfirmPassword($request, $timeout) && $request->expectsJson()) {
            abort(423, __('Password confirmation required.'));
        }

        return parent::handle($request, $next, $redirectToRoute, $passwordTimeoutSeconds);
    }
}
