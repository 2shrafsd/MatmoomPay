<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PaymentPolicy
{
    /**
     * Records of other merchants answer 404 so their existence is not revealed.
     */
    public function view(User $user, Payment $payment): Response
    {
        return $user->roleIn($payment->merchant) !== null ? Response::allow() : Response::denyAsNotFound();
    }

    public function refund(User $user, Payment $payment): bool
    {
        return (bool) $user->roleIn($payment->merchant)?->canRefund();
    }
}
