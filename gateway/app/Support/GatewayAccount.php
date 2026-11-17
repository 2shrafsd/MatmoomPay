<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Merchant;

/**
 * The tenant a request acts for: one merchant in one mode (test or live). Set by the API key
 * middleware for API calls and by the portal middleware for dashboard pages.
 */
final readonly class GatewayAccount
{
    public function __construct(
        public Merchant $merchant,
        public bool $livemode,
    ) {}
}
