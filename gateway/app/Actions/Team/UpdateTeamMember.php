<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\MerchantRole;
use App\Exceptions\GatewayException;
use App\Models\Merchant;
use App\Models\User;

final readonly class UpdateTeamMember
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function changeRole(Merchant $merchant, User $member, MerchantRole $role, User $actor): void
    {
        $this->guardOwner($merchant, $member);

        if ($role === MerchantRole::Owner) {
            throw GatewayException::invalid('role', 'Ownership cannot be assigned here.');
        }

        $merchant->users()->updateExistingPivot($member->id, ['role' => $role->value]);

        if (! $role->canManageTeam()) {
            $this->withdrawInvitationsFrom($merchant, $member);
        }

        $this->recordAuditLog->handle($actor, 'team.role_changed', $merchant, $member, ['role' => $role->value]);
    }

    public function remove(Merchant $merchant, User $member, User $actor): void
    {
        $this->guardOwner($merchant, $member);

        $merchant->users()->detach($member->id);
        $this->withdrawInvitationsFrom($merchant, $member);

        if ($member->current_merchant_id === $merchant->id) {
            $member->current_merchant_id = null;
            $member->save();
        }

        $this->recordAuditLog->handle($actor, 'team.removed', $merchant, $member);
    }

    /**
     * An invitation carries its sender's authority, so it ends when the sender can no longer manage the team.
     */
    private function withdrawInvitationsFrom(Merchant $merchant, User $member): void
    {
        $merchant->invitations()->where('invited_by', $member->id)->whereNull('accepted_at')->delete();
    }

    private function guardOwner(Merchant $merchant, User $member): void
    {
        if ($member->roleIn($merchant) === MerchantRole::Owner) {
            throw GatewayException::cannotUpdate(__('The account owner cannot be changed or removed.'));
        }
    }
}
