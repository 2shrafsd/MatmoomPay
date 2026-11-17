<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CheckoutSession;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CheckoutSessionPolicy
{
    /**
     * Records of other merchants answer 404 so their existence is not revealed.
     */
    public function view(User $user, CheckoutSession $checkoutSession): Response
    {
        return $user->roleIn($checkoutSession->merchant) !== null ? Response::allow() : Response::denyAsNotFound();
    }

    public function cancel(User $user, CheckoutSession $checkoutSession): bool
    {
        return (bool) $user->roleIn($checkoutSession->merchant)?->canManageCustomers();
    }
}
