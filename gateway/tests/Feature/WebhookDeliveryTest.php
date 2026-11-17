<?php

use App\Actions\Webhooks\SendWebhook;
use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function deliveryTo(string $url): WebhookDelivery
{
    $endpoint = WebhookEndpoint::factory()->create(['url' => $url]);

    return WebhookDelivery::create([
        'merchant_id' => $endpoint->merchant_id,
        'webhook_endpoint_id' => $endpoint->id,
        'livemode' => false,
        'event_type' => 'payment.succeeded',
        'payload' => '{"data":{"payment_id":"1"},"event_type":"payment.succeeded"}',
        'status' => WebhookDeliveryStatus::Pending,
    ]);
}

test('webhooks are signed with hmac sha256 of the body and timestamp', function () {
    Http::fake(['*' => Http::response('ok')]);
    $delivery = deliveryTo('https://93.184.215.14/hooks');

    expect(app(SendWebhook::class)->handle($delivery))->toBeTrue();

    Http::assertSent(function (Request $request) use ($delivery) {
        $timestamp = (int) $request->header('matmoom-timestamp')[0];
        $expected = hash_hmac('sha256', $delivery->payload.'-'.$timestamp, $delivery->endpoint->secret);

        return $request->body() === $delivery->payload
            && hash_equals($expected, $request->header('matmoom-signature')[0]);
    });

    expect($delivery->refresh()->status)->toBe(WebhookDeliveryStatus::Delivered);
});

test('webhooks to private network addresses are never sent', function (string $url) {
    Http::fake();
    $delivery = deliveryTo($url);

    expect(app(SendWebhook::class)->handle($delivery))->toBeFalse();

    Http::assertNothingSent();
    expect($delivery->refresh()->last_error)->toContain('private or reserved');
})->with([
    'loopback' => 'http://127.0.0.1/hooks',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data',
    'private range' => 'https://10.0.0.5/hooks',
    'ipv6 loopback' => 'http://[::1]/hooks',
]);

test('a failing endpoint is recorded for retry', function () {
    Http::fake(['*' => Http::response('nope', 500)]);
    $delivery = deliveryTo('https://93.184.215.14/hooks');

    expect(app(SendWebhook::class)->handle($delivery))->toBeFalse();

    $delivery->refresh();
    expect($delivery->status)->toBe(WebhookDeliveryStatus::Pending)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->response_status)->toBe(500);
});
