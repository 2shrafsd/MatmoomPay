<?php

declare(strict_types=1);

namespace App\Actions\Settlements;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Notifications\NotifyMerchantTeam;
use App\Actions\Webhooks\QueueWebhook;
use App\Enums\BalanceTransactionType;
use App\Enums\SettlementStatus;
use App\Enums\TeamNotification;
use App\Enums\WebhookEvent;
use App\Exceptions\GatewayException;
use App\Models\BalanceTransaction;
use App\Models\Merchant;
use App\Models\Settlement;
use App\Models\User;
use App\Notifications\SettlementUpdated;
use Illuminate\Support\Facades\DB;

/**
 * Groups every unsettled live ledger entry of a merchant into one payout batch.
 */
/**
 * Pays out a merchant's live balance. Only money older than the hold period goes out, only to a settlement
 * account an admin verified, and the settlement keeps a copy of that account so a later edit cannot redirect it.
 */
final readonly class CreateSettlement
{
    public function __construct(
        private RecordAuditLog $recordAuditLog,
        private QueueWebhook $queueWebhook,
        private NotifyMerchantTeam $notifyMerchantTeam,
    ) {}

    public function handle(Merchant $merchant, User $admin): Settlement
    {
        if (! $merchant->hasVerifiedSettlementAccount()) {
            throw GatewayException::cannotUpdate(__('Verify the merchant\'s settlement account before paying out.'));
        }

        $cutoff = now()->subDays((int) config('gateway.settlements.hold_days'));

        return DB::transaction(function () use ($merchant, $admin, $cutoff): Settlement {
            // Refunds always count against the payout; new payments wait out the hold period.
            $entries = BalanceTransaction::query()
                ->where('merchant_id', $merchant->id)
                ->where('livemode', true)
                ->whereNull('settlement_id')
                ->where(fn ($query) => $query->where('created_at', '<=', $cutoff)->orWhere('type', BalanceTransactionType::Refund))
                ->lockForUpdate()
                ->get();

            $net = (int) $entries->sum('amount');

            if ($entries->isEmpty() || $net <= 0) {
                throw GatewayException::cannotUpdate(__('There is no positive balance to settle.'));
            }

            $sumOf = fn (BalanceTransactionType $type): int => (int) $entries->where('type', $type)->sum('amount');

            $settlement = Settlement::create([
                'merchant_id' => $merchant->id,
                'currency' => (string) config('gateway.currency'),
                'gross_amount' => $sumOf(BalanceTransactionType::Payment),
                'fee_amount' => abs($sumOf(BalanceTransactionType::Fee)),
                'refund_amount' => abs($sumOf(BalanceTransactionType::Refund)),
                'net_amount' => $net,
                'transactions_count' => $entries->count(),
                'bank_name' => $merchant->settlement_bank_name,
                'account_name' => $merchant->settlement_account_name,
                'iban' => $merchant->settlement_iban,
                'status' => SettlementStatus::Pending,
                'period_start' => $entries->min('created_at'),
                'period_end' => $entries->max('created_at'),
                'created_by' => $admin->id,
            ]);

            BalanceTransaction::query()->whereKey($entries->modelKeys())->update(['settlement_id' => $settlement->id]);

            $this->recordAuditLog->handle($admin, 'settlement.created', $merchant, $settlement, ['net' => $net]);

            return $settlement;
        });
    }

    public function markPaid(Settlement $settlement, string $bankReference, User $admin): Settlement
    {
        return DB::transaction(function () use ($settlement, $bankReference, $admin): Settlement {
            $settlement = Settlement::query()->whereKey($settlement->id)->lockForUpdate()->firstOrFail();

            if ($settlement->status !== SettlementStatus::Pending) {
                throw GatewayException::cannotUpdate(__('Only a settlement awaiting transfer can be marked as transferred.'));
            }

            $settlement->update([
                'status' => SettlementStatus::Paid,
                'bank_reference' => $bankReference,
                'paid_at' => now(),
            ]);

            $this->recordAuditLog->handle($admin, 'settlement.paid', $settlement->merchant, $settlement, ['reference' => $bankReference]);
            $this->queueWebhook->handle($settlement->merchant, true, WebhookEvent::SettlementPaid, self::payload($settlement));
            $this->notifyMerchantTeam->handle($settlement->merchant, TeamNotification::SettlementUpdated, new SettlementUpdated($settlement));

            return $settlement;
        });
    }

    /**
     * The bank bounced the transfer: the balance goes back into the next settlement.
     */
    public function markFailed(Settlement $settlement, string $reason, User $admin): Settlement
    {
        return DB::transaction(function () use ($settlement, $reason, $admin): Settlement {
            $settlement = Settlement::query()->whereKey($settlement->id)->lockForUpdate()->firstOrFail();

            if ($settlement->status !== SettlementStatus::Pending) {
                throw GatewayException::cannotUpdate(__('Only a settlement awaiting transfer can be marked as failed.'));
            }

            $settlement->update(['status' => SettlementStatus::Failed, 'failure_reason' => $reason]);

            BalanceTransaction::query()->where('settlement_id', $settlement->id)->update(['settlement_id' => null]);

            $this->recordAuditLog->handle($admin, 'settlement.failed', $settlement->merchant, $settlement, ['reason' => $reason]);
            $this->queueWebhook->handle($settlement->merchant, true, WebhookEvent::SettlementFailed, self::payload($settlement));
            $this->notifyMerchantTeam->handle($settlement->merchant, TeamNotification::SettlementUpdated, new SettlementUpdated($settlement));

            return $settlement;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(Settlement $settlement): array
    {
        return [
            'id' => $settlement->public_id,
            'status' => $settlement->status->value,
            'gross_amount' => $settlement->gross_amount,
            'fee_amount' => $settlement->fee_amount,
            'refund_amount' => $settlement->refund_amount,
            'net_amount' => $settlement->net_amount,
            'currency' => $settlement->currency,
            'transactions_count' => $settlement->transactions_count,
            'bank_reference' => $settlement->bank_reference,
            'failure_reason' => $settlement->failure_reason,
            'period_start' => $settlement->period_start?->toIso8601String(),
            'period_end' => $settlement->period_end?->toIso8601String(),
            'paid_at' => $settlement->paid_at?->toIso8601String(),
        ];
    }
}
