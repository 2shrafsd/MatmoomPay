<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Merchant;
use App\Models\User;

class MerchantPolicy
{
    public function view(User $user, Merchant $merchant): bool
    {
        return $user->roleIn($merchant) !== null;
    }

    public function update(User $user, Merchant $merchant): bool
    {
        return (bool) $user->roleIn($merchant)?->canManageBusiness();
    }

    public function manageTeam(User $user, Merchant $merchant): bool
    {
        return (bool) $user->roleIn($merchant)?->canManageTeam();
    }

    public function manageDevelopers(User $user, Merchant $merchant): bool
    {
        return (bool) $user->roleIn($merchant)?->canManageDevelopers();
    }

    public function managePaymentLinks(User $user, Merchant $merchant): bool
    {
        return (bool) $user->roleIn($merchant)?->canManagePaymentLinks();
    }

    public function manageCustomers(User $user, Merchant $merchant): bool
    {
        return (bool) $user->roleIn($merchant)?->canManageCustomers();
    }

    public function refund(User $user, Merchant $merchant): bool
    {
        return (bool) $user->roleIn($merchant)?->canRefund();
    }
}
