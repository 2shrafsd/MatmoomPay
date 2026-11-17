<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Audit\RecordAuditLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;

/**
 * Writes account security changes (second factor, passkeys, password resets) to the audit log so admins
 * can trace who weakened or strengthened an account and from where.
 */
final readonly class RecordAccountSecurityEvent
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handleTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->record($event->user, 'user.two_factor_enabled');
    }

    /**
     * Fortify fires this event too when an unfinished setup is discarded; only a confirmed second factor
     * being removed is worth an audit entry.
     */
    public function handleTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        if (($event->user->getPrevious()['two_factor_confirmed_at'] ?? null) !== null) {
            $this->record($event->user, 'user.two_factor_disabled');
        }
    }

    public function handleRecoveryCodesGenerated(RecoveryCodesGenerated $event): void
    {
        $this->record($event->user, 'user.recovery_codes_regenerated');
    }

    public function handlePasskeyRegistered(PasskeyRegistered $event): void
    {
        $this->record($event->user, 'user.passkey_added');
    }

    public function handlePasskeyDeleted(PasskeyDeleted $event): void
    {
        $this->record($event->user, 'user.passkey_removed');
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $this->record($event->user, 'user.password_reset');
    }

    private function record(mixed $user, string $action): void
    {
        if ($user instanceof User) {
            $this->recordAuditLog->handle($user, $action, null, $user);
        }
    }
}
