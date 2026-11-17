<?php

declare(strict_types=1);

namespace App\Actions\Merchants;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\MerchantStatus;
use App\Exceptions\GatewayException;
use App\Models\ApiKey;
use App\Models\Merchant;
use App\Models\User;
use App\Notifications\MerchantStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Platform-admin decisions: approve, reject, suspend, ban or reactivate a merchant.
 */
final readonly class ChangeMerchantStatus
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(Merchant $merchant, MerchantStatus $status, User $admin, ?string $reason = null): Merchant
    {
        if (! $admin->isPlatformAdmin()) {
            throw new InvalidArgumentException('Only platform administrators can change a merchant status.');
        }

        if ($status === MerchantStatus::Active && $merchant->approved_at === null) {
            $this->ensureKycComplete($merchant);
        }

        return DB::transaction(function () use ($merchant, $status, $admin, $reason): Merchant {
            $previous = $merchant->status;

            $merchant->status = $status;
            $merchant->status_reason = $reason;

            if ($status === MerchantStatus::Active && $merchant->approved_at === null) {
                $merchant->approved_at = now();
                $merchant->approved_by = $admin->id;
            }

            $merchant->save();

            if (in_array($status, [MerchantStatus::Suspended, MerchantStatus::Rejected], true)) {
                ApiKey::query()->where('merchant_id', $merchant->id)->where('livemode', true)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            }

            // A ban shuts the merchant out of test mode too, so every key stops working.
            if ($status === MerchantStatus::Banned) {
                ApiKey::query()->where('merchant_id', $merchant->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            }

            $this->recordAuditLog->handle($admin, 'merchant.status_changed', $merchant, $merchant, [
                'from' => $previous->value,
                'to' => $status->value,
                'reason' => $reason,
            ]);

            if ($previous !== $status) {
                Notification::send($merchant->managers(), (new MerchantStatusChanged($merchant, $status, $reason))->afterCommit());
            }

            return $merchant;
        });
    }

    /**
     * A merchant is approved for the first time only after every required document was accepted.
     */
    private function ensureKycComplete(Merchant $merchant): void
    {
        $accepted = $merchant->documents()
            ->where('status', DocumentStatus::Approved)
            ->pluck('type')
            ->map(fn (DocumentType|string $type) => $type instanceof DocumentType ? $type : DocumentType::from($type));

        $missing = array_filter(DocumentType::required(), fn (DocumentType $type) => ! $accepted->contains($type));

        if ($missing !== []) {
            throw GatewayException::cannotUpdate(__('Accept these documents before approving: :documents', [
                'documents' => implode('، ', array_map(fn (DocumentType $type) => $type->label(), $missing)),
            ]));
        }
    }

    public function updatePricing(Merchant $merchant, int $rateBps, int $fixed, User $admin): Merchant
    {
        $previous = ['rate_bps' => $merchant->fee_rate_bps, 'fixed' => $merchant->fee_fixed];

        $merchant->fee_rate_bps = $rateBps;
        $merchant->fee_fixed = $fixed;
        $merchant->save();

        $this->recordAuditLog->handle($admin, 'merchant.pricing_changed', $merchant, $merchant, [
            'from' => $previous['rate_bps'].'bps+'.$previous['fixed'],
            'to' => $rateBps.'bps+'.$fixed,
        ]);

        return $merchant;
    }
}
