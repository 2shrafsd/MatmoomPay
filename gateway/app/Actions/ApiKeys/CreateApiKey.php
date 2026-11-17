<?php

declare(strict_types=1);

namespace App\Actions\ApiKeys;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\ApiKeyType;
use App\Models\ApiKey;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Str;

final readonly class CreateApiKey
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /**
     * Returns the key and its plain token. A secret token is only available here: it is stored as a hash.
     *
     * @return array{0: ApiKey, 1: string}
     */
    public function handle(Merchant $merchant, bool $livemode, ApiKeyType $type, string $name, ?User $actor): array
    {
        $prefix = $type->prefix($livemode);
        $token = $prefix.Str::random(40);

        $key = ApiKey::create([
            'merchant_id' => $merchant->id,
            'livemode' => $livemode,
            'type' => $type,
            'name' => $name,
            'token_prefix' => $prefix,
            'token_hash' => ApiKey::hashToken($token),
            'publishable_token' => $type === ApiKeyType::Publishable ? $token : null,
            'last_four' => substr($token, -4),
            'created_by' => $actor?->id,
        ]);

        $this->recordAuditLog->handle($actor, 'api_key.created', $merchant, $key, ['type' => $type->value, 'livemode' => $livemode]);

        return [$key, $token];
    }
}
