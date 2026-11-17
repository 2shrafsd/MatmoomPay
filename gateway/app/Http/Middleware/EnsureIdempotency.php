<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Api\ApiResponse;
use App\Models\IdempotencyKey;
use App\Support\GatewayAccount;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replays the stored response when a POST is retried with the same Idempotency-Key header, so a
 * network retry can never create a second session, intent or refund.
 */
class EnsureIdempotency
{
    /**
     * Minutes after which an unfinished request is treated as abandoned.
     */
    private const ABANDONED_AFTER_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $request->isMethod('POST') || $key === null || $key === '') {
            return $next($request);
        }

        if (strlen($key) > 255) {
            return ApiResponse::error(4000, 'The Idempotency-Key header must not exceed 255 characters.', 400);
        }

        $account = app(GatewayAccount::class);
        $fingerprint = hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());

        $existing = IdempotencyKey::query()
            ->where('merchant_id', $account->merchant->id)
            ->where('livemode', $account->livemode)
            ->where('key', $key)
            ->where('expires_at', '>', now())
            ->first();

        // A request that never recorded its response (the process died) stops blocking the key after a few minutes.
        if ($existing !== null && $existing->response_status === null && $existing->created_at?->lt(now()->subMinutes(self::ABANDONED_AFTER_MINUTES))) {
            $existing->delete();
            $existing = null;
        }

        if ($existing !== null) {
            return $this->replay($existing, $fingerprint);
        }

        // Only an expired record may be cleared here. A live one belongs to a concurrent request, and the
        // unique index below turns that race into a 409 instead of a second execution.
        IdempotencyKey::query()
            ->where('merchant_id', $account->merchant->id)
            ->where('livemode', $account->livemode)
            ->where('key', $key)
            ->where('expires_at', '<=', now())
            ->delete();

        try {
            $record = IdempotencyKey::create([
                'merchant_id' => $account->merchant->id,
                'livemode' => $account->livemode,
                'key' => $key,
                'request_fingerprint' => $fingerprint,
                'expires_at' => now()->addHours((int) config('gateway.api.idempotency_ttl_hours')),
            ]);
        } catch (UniqueConstraintViolationException) {
            return ApiResponse::error(4004, 'A request with this Idempotency-Key is already being processed.', 409);
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 500) {
            $record->delete();

            return $response;
        }

        $record->update([
            'response_status' => $response->getStatusCode(),
            'response_body' => (string) $response->getContent(),
        ]);

        return $response;
    }

    private function replay(IdempotencyKey $existing, string $fingerprint): Response
    {
        if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
            return ApiResponse::error(4000, 'This Idempotency-Key was already used with different request parameters.', 400);
        }

        if ($existing->response_status === null) {
            return ApiResponse::error(4004, 'A request with this Idempotency-Key is already being processed.', 409);
        }

        return JsonResponse::fromJsonString((string) $existing->response_body, $existing->response_status, ['Idempotent-Replayed' => 'true']);
    }
}
