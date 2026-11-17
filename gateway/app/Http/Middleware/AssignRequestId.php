<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every API request an ID, returned in the X-Request-Id header and attached to every log line, so a
 * merchant can quote it to support and we can find the exact request.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = 'req_'.Str::random(24);

        Context::add('request_id', $id);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
