<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MerchantDocument;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MerchantDocumentPolicy
{
    /**
     * Owners and admins of the merchant, or platform reviewers. Everyone else gets a 404.
     */
    public function view(User $user, MerchantDocument $document): Response
    {
        if ($this->isReviewer($user) || $user->roleIn($document->merchant)?->canManageBusiness()) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    public function delete(User $user, MerchantDocument $document): bool
    {
        return (bool) $user->roleIn($document->merchant)?->canManageBusiness();
    }

    /**
     * Platform reviewers open identity documents only under the same two-factor rule as the admin panel.
     */
    private function isReviewer(User $user): bool
    {
        return $user->isPlatformAdmin()
            && (! config('security.admin_requires_two_factor') || $user->two_factor_confirmed_at !== null);
    }
}
