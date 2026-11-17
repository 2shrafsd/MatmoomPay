<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Refund;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RefundPolicy
{
    /**
     * Records of other merchants answer 404 so their existence is not revealed.
     */
    public function view(User $user, Refund $refund): Response
    {
        return $user->roleIn($refund->merchant) !== null ? Response::allow() : Response::denyAsNotFound();
    }
}
