<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Which dashboard a signed-in person is working in. Shared pages (profile, security, password confirmation)
 * render inside that same dashboard so nobody is thrown out of the interface they came from.
 */
final class Panel
{
    public const SESSION_KEY = 'panel';

    public const ADMIN = 'admin';

    public const MERCHANT = 'merchant';

    public static function remember(string $panel): void
    {
        if (in_array($panel, [self::ADMIN, self::MERCHANT], true)) {
            session()->put(self::SESSION_KEY, $panel);
        }
    }

    public static function current(?User $user = null): string
    {
        $user ??= auth()->user();

        if (! $user instanceof User || ! $user->isPlatformAdmin()) {
            return self::MERCHANT;
        }

        $remembered = session(self::SESSION_KEY);

        if (in_array($remembered, [self::ADMIN, self::MERCHANT], true)) {
            return $remembered;
        }

        return $user->merchants()->exists() ? self::MERCHANT : self::ADMIN;
    }

    public static function isAdmin(?User $user = null): bool
    {
        return self::current($user) === self::ADMIN;
    }
}
