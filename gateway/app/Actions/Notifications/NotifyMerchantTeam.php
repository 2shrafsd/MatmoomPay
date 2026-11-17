<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Enums\TeamNotification;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Emails the team members whose role covers the event and who kept that email switched on. Sent after the
 * surrounding transaction commits, in each person's own language.
 */
final class NotifyMerchantTeam
{
    public function handle(Merchant $merchant, TeamNotification $type, BaseNotification $notification): void
    {
        $recipients = $merchant->users()
            ->wherePivotIn('role', array_map(fn ($role) => $role->value, $type->roles()))
            ->get()
            ->filter(fn (User $user): bool => $user->wantsNotification($type));

        if ($recipients->isNotEmpty()) {
            DB::afterCommit(fn () => Notification::send($recipients, $notification));
        }
    }
}
