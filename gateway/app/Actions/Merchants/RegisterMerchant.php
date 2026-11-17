<?php

declare(strict_types=1);

namespace App\Actions\Merchants;

use App\Actions\ApiKeys\CreateApiKey;
use App\Actions\Audit\RecordAuditLog;
use App\Data\MerchantProfileData;
use App\Enums\ApiKeyType;
use App\Enums\MerchantRole;
use App\Enums\MerchantStatus;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Opens a merchant account under review. Test mode works immediately; live payments wait for approval.
 */
final readonly class RegisterMerchant
{
    public function __construct(
        private CreateApiKey $createApiKey,
        private RecordAuditLog $recordAuditLog,
    ) {}

    public function handle(User $owner, MerchantProfileData $profile): Merchant
    {
        return DB::transaction(function () use ($owner, $profile): Merchant {
            $merchant = new Merchant($profile->toAttributes());
            $merchant->status = MerchantStatus::Pending;
            $merchant->fee_rate_bps = (int) config('gateway.fees.rate_bps');
            $merchant->fee_fixed = (int) config('gateway.fees.fixed');
            $merchant->save();

            $merchant->users()->attach($owner->id, ['role' => MerchantRole::Owner->value]);
            $owner->switchMerchant($merchant);

            $this->createApiKey->handle($merchant, false, ApiKeyType::Publishable, 'Default publishable key', $owner);
            $this->recordAuditLog->handle($owner, 'merchant.registered', $merchant, $merchant);

            return $merchant;
        });
    }
}
