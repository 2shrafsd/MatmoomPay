<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PaymentLink;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PaymentLinkPolicy
{
    /**
     * Records of other merchants answer 404 so their existence is not revealed.
     */
    public function view(User $user, PaymentLink $paymentLink): Response
    {
        return $user->roleIn($paymentLink->merchant) !== null ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, PaymentLink $paymentLink): bool
    {
        return (bool) $user->roleIn($paymentLink->merchant)?->canManagePaymentLinks();
    }
}
