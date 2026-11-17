<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant scoping for every merchant-owned record. All reads go through forAccount()
 * so a merchant only ever sees its own data in the mode (test or live) it is using.
 */
trait BelongsToMerchant
{
    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class)->withTrashed();
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function forAccount(Builder $query, Merchant $merchant, bool $livemode): void
    {
        $query->where($this->qualifyColumn('merchant_id'), $merchant->getKey())
            ->where($this->qualifyColumn('livemode'), $livemode);
    }

    public function belongsToAccount(Merchant $merchant, bool $livemode): bool
    {
        return (int) $this->getAttribute('merchant_id') === (int) $merchant->getKey()
            && (bool) $this->getAttribute('livemode') === $livemode;
    }
}
