<?php

declare(strict_types=1);

namespace App\Actions\Webhooks;

use App\Actions\Audit\RecordAuditLog;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Support\GatewayAccount;
use Illuminate\Support\Str;

final readonly class SaveWebhookEndpoint
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public static function newSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }

    public function handle(GatewayAccount $account, string $url, bool $isActive, User $actor): WebhookEndpoint
    {
        $endpoint = WebhookEndpoint::query()->forAccount($account->merchant, $account->livemode)->first();

        if ($endpoint === null) {
            $endpoint = WebhookEndpoint::create([
                'merchant_id' => $account->merchant->id,
                'livemode' => $account->livemode,
                'url' => $url,
                'secret' => self::newSecret(),
                'is_active' => $isActive,
            ]);
        } else {
            $endpoint->update(['url' => $url, 'is_active' => $isActive]);
        }

        $this->recordAuditLog->handle($actor, 'webhook_endpoint.saved', $account->merchant, $endpoint, ['url' => $url, 'livemode' => $account->livemode]);

        return $endpoint;
    }

    public function rotateSecret(WebhookEndpoint $endpoint, User $actor): WebhookEndpoint
    {
        $endpoint->update(['secret' => self::newSecret()]);

        $this->recordAuditLog->handle($actor, 'webhook_endpoint.secret_rotated', $endpoint->merchant, $endpoint);

        return $endpoint;
    }
}
