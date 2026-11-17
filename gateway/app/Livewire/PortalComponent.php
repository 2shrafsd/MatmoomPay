<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\MerchantRole;
use App\Models\Merchant;
use App\Support\GatewayAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Base for merchant portal pages. The account is bound by EnsureMerchantAccount, which Livewire
 * re-runs on every update request, so these values are trustworthy inside actions.
 */
abstract class PortalComponent extends Component
{
    protected function account(): GatewayAccount
    {
        return app(GatewayAccount::class);
    }

    protected function merchant(): Merchant
    {
        return $this->account()->merchant;
    }

    protected function livemode(): bool
    {
        return $this->account()->livemode;
    }

    protected function role(): MerchantRole
    {
        return Auth::user()?->roleIn($this->merchant()) ?? abort(403);
    }

    /**
     * Fails with 404 when a record belongs to another merchant or to the other mode.
     */
    protected function ensureInAccount(Model $record): void
    {
        if ((int) $record->getAttribute('merchant_id') !== $this->merchant()->id || (bool) $record->getAttribute('livemode') !== $this->livemode()) {
            abort(404);
        }
    }
}
