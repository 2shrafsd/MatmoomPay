<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Merchant;
use App\Models\MerchantInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class AcceptTeamInvitation
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /**
     * Returns null when the token is unknown, expired, used, or addressed to another email.
     */
    public function handle(User $user, string $token): ?Merchant
    {
        return DB::transaction(function () use ($user, $token): ?Merchant {
            $invitation = MerchantInvitation::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if ($invitation === null || ! $invitation->isPending() || ! hash_equals($invitation->email, Str::lower($user->email))) {
                return null;
            }

            $merchant = $invitation->merchant;

            // An archived or banned business takes no new members.
            if ($merchant->trashed() || $merchant->status->blocksPortal()) {
                return null;
            }

            // Joining never changes the role of someone who is already a member.
            if ($user->roleIn($merchant) === null) {
                $merchant->users()->attach($user->id, ['role' => $invitation->role->value]);
            }

            $invitation->update(['accepted_at' => now()]);
            $user->switchMerchant($merchant);

            $this->recordAuditLog->handle($user, 'team.joined', $merchant, $invitation, ['role' => $invitation->role->value]);

            return $merchant;
        });
    }
}
