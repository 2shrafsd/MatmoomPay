<?php

declare(strict_types=1);

namespace App\Actions\Merchants;

use App\Actions\Audit\RecordAuditLog;
use App\Data\MerchantProfileData;
use App\Exceptions\GatewayException;
use App\Models\Merchant;
use App\Models\User;
use App\Notifications\SettlementAccountChanged;
use Illuminate\Support\Facades\Notification;

final readonly class UpdateMerchantProfile
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(Merchant $merchant, MerchantProfileData $profile, User $actor): Merchant
    {
        $merchant->fill($profile->toAttributes());
        $changed = array_keys($merchant->getDirty());
        $merchant->save();

        if ($changed !== []) {
            $this->recordAuditLog->handle($actor, 'merchant.profile_updated', $merchant, $merchant, ['fields' => implode(',', $changed)]);
        }

        return $merchant;
    }

    /**
     * Payout details are a fraud target. A change is always audited (without the account number), needs an
     * admin to verify it again before the next payout, and is announced to every owner and admin by email so
     * a hijacked session cannot redirect money quietly.
     */
    public function updateSettlementAccount(Merchant $merchant, string $bankName, string $accountName, string $iban, User $actor): Merchant
    {
        $previousIban = $merchant->settlement_iban;

        $merchant->fill([
            'settlement_bank_name' => $bankName,
            'settlement_account_name' => $accountName,
            'settlement_iban' => $iban,
        ]);

        if (! $merchant->isDirty()) {
            return $merchant;
        }

        $merchant->forceFill(['settlement_account_verified_at' => null, 'settlement_account_verified_by' => null])->save();

        $this->recordAuditLog->handle($actor, 'merchant.settlement_account_updated', $merchant, $merchant, [
            'bank' => $bankName,
            'iban_last4' => substr($iban, -4),
            'previous_iban_last4' => $previousIban !== null ? substr($previousIban, -4) : null,
        ]);

        Notification::send($merchant->managers(), new SettlementAccountChanged($merchant, $actor));

        return $merchant;
    }

    public function verifySettlementAccount(Merchant $merchant, User $admin): Merchant
    {
        if (blank($merchant->settlement_iban)) {
            throw GatewayException::cannotUpdate(__('The merchant has not added a settlement account yet.'));
        }

        $merchant->forceFill(['settlement_account_verified_at' => now(), 'settlement_account_verified_by' => $admin->id])->save();

        $this->recordAuditLog->handle($admin, 'merchant.settlement_account_verified', $merchant, $merchant, ['iban_last4' => substr((string) $merchant->settlement_iban, -4)]);

        return $merchant;
    }
}
