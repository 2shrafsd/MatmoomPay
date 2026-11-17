<?php

declare(strict_types=1);

namespace App\Actions\ApiKeys;

use App\Actions\Audit\RecordAuditLog;
use App\Models\ApiKey;
use App\Models\User;

final readonly class RevokeApiKey
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(ApiKey $key, User $actor): void
    {
        if ($key->isRevoked()) {
            return;
        }

        $key->update(['revoked_at' => now()]);

        $this->recordAuditLog->handle($actor, 'api_key.revoked', $key->merchant, $key, ['type' => $key->type->value, 'livemode' => $key->livemode]);
    }
}
