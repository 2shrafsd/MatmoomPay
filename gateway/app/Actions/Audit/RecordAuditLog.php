<?php

declare(strict_types=1);

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes an append-only audit entry for security-relevant actions. Never pass secrets or card data in $properties.
 */
final class RecordAuditLog
{
    /**
     * @param  array<string, scalar|null>  $properties
     */
    public function handle(?User $actor, string $action, ?Merchant $merchant = null, ?Model $subject = null, array $properties = []): AuditLog
    {
        $request = app()->runningInConsole() ? null : request();

        return AuditLog::create([
            'actor_id' => $actor?->id,
            'merchant_id' => $merchant?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
            'properties' => $properties === [] ? null : $properties,
            'created_at' => now(),
        ]);
    }
}
