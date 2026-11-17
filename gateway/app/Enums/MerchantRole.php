<?php

declare(strict_types=1);

namespace App\Enums;

enum MerchantRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Developer = 'developer';
    case Finance = 'finance';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Owner'),
            self::Admin => __('Administrator'),
            self::Developer => __('Developer'),
            self::Finance => __('Finance'),
            self::Viewer => __('Viewer'),
        };
    }

    public function canManageTeam(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canManageBusiness(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canManageDevelopers(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Developer], true);
    }

    public function canRefund(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Finance], true);
    }

    public function canManagePaymentLinks(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Finance], true);
    }

    public function canManageCustomers(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Developer, self::Finance], true);
    }

    /**
     * Roles that a team manager may hand out. Ownership is never assignable through invitations.
     *
     * @return list<self>
     */
    public static function assignable(): array
    {
        return [self::Admin, self::Developer, self::Finance, self::Viewer];
    }
}
