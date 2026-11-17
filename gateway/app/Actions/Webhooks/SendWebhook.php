<?php

declare(strict_types=1);

namespace App\Actions\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Exceptions\UnsafeOutboundUrl;
use App\Models\WebhookDelivery;
use App\Support\OutboundUrlGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Performs one signed delivery attempt.
 *
 * Signature: hex HMAC-SHA256 of "{raw body}-{timestamp}" keyed with the endpoint secret, sent in the
 * matmoom-signature header with the timestamp in matmoom-timestamp.
 */
final class SendWebhook
{
    public static function sign(string $body, int $timestamp, string $secret): string
    {
        return hash_hmac('sha256', $body.'-'.$timestamp, $secret);
    }

    public function handle(WebhookDelivery $delivery): bool
    {
        $endpoint = $delivery->endpoint;

        if ($endpoint === null || ! $endpoint->is_active) {
            return $this->record($delivery, false, null, null, 'Webhook endpoint removed or disabled.');
        }

        try {
            $target = OutboundUrlGuard::resolve($endpoint->url);
        } catch (UnsafeOutboundUrl $exception) {
            return $this->record($delivery, false, null, null, $exception->getMessage());
        }

        $timestamp = now()->getTimestamp();

        try {
            $response = Http::timeout((int) config('gateway.webhooks.timeout_seconds'))
                ->connectTimeout(5)
                ->withoutRedirecting()
                ->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"]]])
                ->withHeaders([
                    'User-Agent' => 'MatmoomPay-Webhooks/1.0',
                    config('gateway.webhooks.signature_header') => self::sign($delivery->payload, $timestamp, $endpoint->secret),
                    config('gateway.webhooks.timestamp_header') => (string) $timestamp,
                    'matmoom-event-id' => (string) (json_decode($delivery->payload, true)['id'] ?? ''),
                ])
                ->withBody($delivery->payload, 'application/json')
                ->post($endpoint->url);
        } catch (ConnectionException $exception) {
            return $this->record($delivery, false, null, null, Str::limit($exception->getMessage(), 480));
        }

        return $this->record(
            $delivery,
            $response->successful(),
            $response->status(),
            Str::limit($response->body(), 990),
            $response->successful() ? null : 'Endpoint answered with HTTP '.$response->status().'.',
        );
    }

    private function record(WebhookDelivery $delivery, bool $delivered, ?int $status, ?string $excerpt, ?string $error): bool
    {
        $delivery->fill([
            'attempts' => $delivery->attempts + 1,
            'status' => $delivered ? WebhookDeliveryStatus::Delivered : WebhookDeliveryStatus::Pending,
            'response_status' => $status,
            'response_excerpt' => $excerpt,
            'last_error' => $error,
            'delivered_at' => $delivered ? now() : null,
        ])->save();

        return $delivered;
    }
}
