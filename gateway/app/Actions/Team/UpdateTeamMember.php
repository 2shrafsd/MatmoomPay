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

        $this->recordAuditLog->handle($actor, 'team.role_changed', $merchant, $member, ['role' => $role->value]);
    }

    public function remove(Merchant $merchant, User $member, User $actor): void
    {
        $this->guardOwner($merchant, $member);

        $merchant->users()->detach($member->id);

        if ($member->current_merchant_id === $merchant->id) {
            $member->current_merchant_id = null;
            $member->save();
        }

        $this->recordAuditLog->handle($actor, 'team.removed', $merchant, $member);
    }

    private function guardOwner(Merchant $merchant, User $member): void
    {
        if ($member->roleIn($merchant) === MerchantRole::Owner) {
            throw GatewayException::cannotUpdate(__('The account owner cannot be changed or removed.'));
        }
    }
}
