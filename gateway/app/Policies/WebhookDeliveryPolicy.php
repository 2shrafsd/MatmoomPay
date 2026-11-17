<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookDelivery;
use Illuminate\Auth\Access\Response;

class WebhookDeliveryPolicy
{
    /**
     * Records of other merchants answer 404 so their existence is not revealed.
     */
    public function view(User $user, WebhookDelivery $webhookDelivery): Response
    {
        return $user->roleIn($webhookDelivery->merchant) !== null ? Response::allow() : Response::denyAsNotFound();
    }

    public function resend(User $user, WebhookDelivery $webhookDelivery): bool
    {
        return (bool) $user->roleIn($webhookDelivery->merchant)?->canManageDevelopers();
    }
}
