<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\ApiKeyType;
use App\Http\Api\ApiResponse;
use App\Models\ApiKey;
use App\Support\GatewayAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates gateway API calls with a secret key sent in the matmoom-api-key header and binds
 * the merchant/mode it belongs to for the rest of the request.
 */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) ($request->header((string) config('gateway.api.key_header')) ?? $request->bearerToken());

        if ($token === '' || strlen($token) > 100) {
            return $this->unauthorized();
        }

        $key = ApiKey::query()
            ->with('merchant')
            ->where('token_hash', ApiKey::hashToken($token))
            ->first();

        if ($key === null || $key->isRevoked() || $key->type !== ApiKeyType::Secret) {
            return $this->unauthorized();
        }

        if (! $key->merchant->canCollect($key->livemode)) {
            return ApiResponse::error(4030, 'This merchant account is not allowed to use the API in this mode.', 403);
        }

        if ($key->last_used_at === null || $key->last_used_at->lt(now()->subMinute())) {
            $key->update(['last_used_at' => now()]);
        }

        $account = new GatewayAccount($key->merchant, $key->livemode);
        app()->instance(GatewayAccount::class, $account);
        $request->attributes->set('api_key', $key);

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return ApiResponse::error(4010, 'No valid API key provided.', 401);
    }
}
