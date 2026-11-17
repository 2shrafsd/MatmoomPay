<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ApiKeyPolicy
{
    /**
     * Records of other merchants answer 404 so their existence is not revealed.
     */
    public function view(User $user, ApiKey $apiKey): Response
    {
        return $user->roleIn($apiKey->merchant) !== null ? Response::allow() : Response::denyAsNotFound();
    }

    public function revoke(User $user, ApiKey $apiKey): bool
    {
        return (bool) $user->roleIn($apiKey->merchant)?->canManageDevelopers();
    }
}
