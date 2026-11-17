<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Panel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers whether the person is in the admin panel or the merchant portal, so the settings pages open in it.
 */
class RememberPanel
{
    public function handle(Request $request, Closure $next, string $panel): Response
    {
        if ($request->hasSession() && $request->isMethod('GET')) {
            Panel::remember($panel);
        }

        return $next($request);
    }
}
