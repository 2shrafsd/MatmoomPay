<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\MerchantRole;
use App\Exceptions\GatewayException;
use App\Models\Merchant;
use App\Models\MerchantInvitation;
use App\Models\User;
use App\Notifications\TeamInvitation;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final readonly class InviteTeamMember
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(Merchant $merchant, string $email, MerchantRole $role, User $inviter): MerchantInvitation
    {
        $email = Str::lower($email);

        if ($role === MerchantRole::Owner) {
            throw GatewayException::invalid('role', 'Ownership cannot be granted through an invitation.');
        }

        if ($merchant->users()->where('email', $email)->exists()) {
            throw GatewayException::alreadyExists(__('This person is already on the team.'));
        }

        if (RateLimiter::tooManyAttempts('team-invites:'.$merchant->id, 10)) {
            throw GatewayException::cannotUpdate(__('Too many invitations. Try again in an hour.'));
        }

        RateLimiter::hit('team-invites:'.$merchant->id, 3600);

        $merchant->invitations()->where('email', $email)->whereNull('accepted_at')->delete();

        $token = Str::random(48);

        $invitation = MerchantInvitation::create([
            'merchant_id' => $merchant->id,
            'email' => $email,
            'role' => $role,
            'token_hash' => hash('sha256', $token),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(7),
        ]);

        Notification::route('mail', $email)->notify(new TeamInvitation($merchant, $inviter, $token));

        $this->recordAuditLog->handle($inviter, 'team.invited', $merchant, $invitation, ['email' => $email, 'role' => $role->value]);

        return $invitation;
    }
}
