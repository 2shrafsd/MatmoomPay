<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\MerchantRole;
use App\Exceptions\GatewayException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The platform owner's controls over any user account: edit, ban, admin rights, two-factor reset and delete.
 *
 * An admin can never ban, demote or delete themselves, and the platform always keeps at least one admin.
 */
final readonly class ManageUserAccount
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function update(User $user, string $name, string $email, User $admin): User
    {
        $this->ensureAdmin($admin);

        $user->name = $name;

        if ($user->email !== $email) {
            $user->email = $email;
            $user->email_verified_at = null;
        }

        $changed = array_keys($user->getDirty());
        $user->save();

        if ($changed !== []) {
            $this->recordAuditLog->handle($admin, 'user.updated', null, $user, ['fields' => implode(',', $changed)]);
        }

        return $user;
    }

    public function ban(User $user, string $reason, User $admin): void
    {
        $this->ensureAdmin($admin);
        $this->ensureNotSelf($user, $admin, __('You cannot ban your own account.'));

        if ($user->isPlatformAdmin()) {
            throw GatewayException::cannotUpdate(__('Remove admin rights before banning this person.'));
        }

        $user->forceFill(['banned_at' => now(), 'ban_reason' => $reason, 'remember_token' => null])->save();

        // Any open sessions end on their next request; stored database sessions are cleared right away.
        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        $this->recordAuditLog->handle($admin, 'user.banned', null, $user, ['reason' => $reason]);
    }

    public function unban(User $user, User $admin): void
    {
        $this->ensureAdmin($admin);

        $user->forceFill(['banned_at' => null, 'ban_reason' => null])->save();

        $this->recordAuditLog->handle($admin, 'user.unbanned', null, $user);
    }

    public function setPlatformAdmin(User $user, bool $isAdmin, User $admin): void
    {
        $this->ensureAdmin($admin);

        if (! $isAdmin) {
            $this->ensureNotSelf($user, $admin, __('You cannot remove your own admin rights.'));
        }

        if ($isAdmin && $user->isBanned()) {
            throw GatewayException::cannotUpdate(__('Lift the ban before giving admin rights.'));
        }

        $user->forceFill(['is_platform_admin' => $isAdmin])->save();

        $this->recordAuditLog->handle($admin, $isAdmin ? 'user.admin_granted' : 'user.admin_revoked', null, $user);
    }

    /**
     * Clears two-factor so a person who lost their phone can set it up again after signing in.
     */
    public function resetTwoFactor(User $user, User $admin): void
    {
        $this->ensureAdmin($admin);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->recordAuditLog->handle($admin, 'user.two_factor_reset', null, $user);
    }

    public function markEmailVerified(User $user, User $admin): void
    {
        $this->ensureAdmin($admin);

        if ($user->hasVerifiedEmail()) {
            return;
        }

        $user->markEmailAsVerified();

        $this->recordAuditLog->handle($admin, 'user.email_verified', null, $user);
    }

    /**
     * Deletes the account. A sole owner must hand the business over (or the business is removed) first,
     * otherwise a merchant would be left without anyone who can manage it.
     */
    public function delete(User $user, User $admin): void
    {
        $this->ensureAdmin($admin);
        $this->ensureNotSelf($user, $admin, __('You cannot delete your own account from here.'));

        if ($user->isPlatformAdmin()) {
            throw GatewayException::cannotUpdate(__('Remove admin rights before deleting this person.'));
        }

        $ownedAlone = $user->merchants()
            ->wherePivot('role', MerchantRole::Owner->value)
            ->get()
            ->filter(fn ($merchant) => $merchant->users()->wherePivot('role', MerchantRole::Owner->value)->count() === 1);

        if ($ownedAlone->isNotEmpty()) {
            throw GatewayException::cannotUpdate(__('This person is the only owner of :merchants. Archive or delete the business first.', [
                'merchants' => $ownedAlone->pluck('name')->implode('، '),
            ]));
        }

        DB::transaction(function () use ($user, $admin): void {
            $this->recordAuditLog->handle($admin, 'user.deleted', null, null, ['user' => $user->id, 'email' => $user->email]);
            $user->delete();
        });
    }

    private function ensureNotSelf(User $user, User $admin, string $message): void
    {
        if ($user->is($admin)) {
            throw GatewayException::cannotUpdate($message);
        }
    }

    private function ensureAdmin(User $admin): void
    {
        if (! $admin->isPlatformAdmin()) {
            throw new InvalidArgumentException('Only platform administrators can manage user accounts.');
        }
    }
}
