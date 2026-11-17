<?php

declare(strict_types=1);

namespace App\Actions\Merchants;

use App\Actions\Audit\RecordAuditLog;
use App\Exceptions\GatewayException;
use App\Models\ApiKey;
use App\Models\Merchant;
use App\Models\MerchantDocument;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Removing a merchant, from the platform owner's side.
 *
 * Archiving (soft delete) is always possible and can be undone: the merchant disappears from the portal,
 * every API key stops working and its records stay intact for accounting. A permanent delete is only
 * allowed for a merchant that never touched money, because financial records must outlive their merchant.
 */
final readonly class ArchiveMerchant
{
    /**
     * Tables that hold money history and block a permanent delete.
     *
     * @var list<string>
     */
    private const MONEY_TABLES = [
        'checkout_sessions', 'payment_intents', 'payments', 'refunds', 'subscriptions',
        'subscription_invoices', 'balance_transactions', 'settlements',
    ];

    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function archive(Merchant $merchant, User $admin, ?string $reason = null): void
    {
        $this->ensureAdmin($admin);

        if ($merchant->trashed()) {
            return;
        }

        DB::transaction(function () use ($merchant, $admin, $reason): void {
            ApiKey::query()->where('merchant_id', $merchant->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $merchant->status_reason = $reason ?? $merchant->status_reason;
            $merchant->save();
            $merchant->delete();

            $this->recordAuditLog->handle($admin, 'merchant.archived', $merchant, $merchant, ['reason' => $reason]);
        });
    }

    /**
     * Brings an archived merchant back. Its old keys stay revoked, so the team creates new ones.
     */
    public function restore(Merchant $merchant, User $admin): void
    {
        $this->ensureAdmin($admin);

        if (! $merchant->trashed()) {
            return;
        }

        $merchant->restore();

        $this->recordAuditLog->handle($admin, 'merchant.restored', $merchant, $merchant);
    }

    public function hasMoneyHistory(Merchant $merchant): bool
    {
        foreach (self::MONEY_TABLES as $table) {
            if (DB::table($table)->where('merchant_id', $merchant->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Erases a merchant that never processed money, with its team links, keys, webhooks and documents.
     */
    public function destroy(Merchant $merchant, User $admin): void
    {
        $this->ensureAdmin($admin);

        if ($this->hasMoneyHistory($merchant)) {
            throw GatewayException::cannotUpdate(__('This merchant has payment history, so it cannot be deleted permanently. Archive or ban it instead.'));
        }

        $paths = $merchant->documents()->pluck('path')->all();

        DB::transaction(function () use ($merchant, $admin): void {
            // The audit entry outlives the merchant (its merchant_id is nulled), so it keeps the name.
            $this->recordAuditLog->handle($admin, 'merchant.deleted', null, null, [
                'merchant' => $merchant->public_id,
                'name' => $merchant->name,
            ]);

            WebhookEndpoint::query()->where('merchant_id', $merchant->id)->delete();
            User::query()->where('current_merchant_id', $merchant->id)->update(['current_merchant_id' => null]);
            $merchant->forceDelete();
        });

        if ($paths !== []) {
            Storage::disk(MerchantDocument::disk())->delete($paths);
        }
    }

    private function ensureAdmin(User $admin): void
    {
        if (! $admin->isPlatformAdmin()) {
            throw new InvalidArgumentException('Only platform administrators can remove a merchant.');
        }
    }
}
