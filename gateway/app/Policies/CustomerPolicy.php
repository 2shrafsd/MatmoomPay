<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CustomerPolicy
{
    /**
     * Records of other merchants answer 404 so their existence is not revealed.
     */
    public function view(User $user, Customer $customer): Response
    {
        return $user->roleIn($customer->merchant) !== null ? Response::allow() : Response::denyAsNotFound();
    }

    public function delete(User $user, Customer $customer): bool
    {
        return (bool) $user->roleIn($customer->merchant)?->canManageCustomers();
    }
}
