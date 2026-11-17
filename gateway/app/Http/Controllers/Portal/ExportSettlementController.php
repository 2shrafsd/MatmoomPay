<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Actions\Audit\RecordAuditLog;
use App\Models\BalanceTransaction;
use App\Models\Settlement;
use App\Support\GatewayAccount;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams every ledger entry a settlement paid out, for reconciling the bank transfer.
 */
class ExportSettlementController
{
    public function __invoke(Request $request, GatewayAccount $account, RecordAuditLog $recordAuditLog, string $settlement): StreamedResponse
    {
        $settlement = Settlement::query()->where('merchant_id', $account->merchant->id)->where('public_id', $settlement)->firstOrFail();

        $recordAuditLog->handle($request->user(), 'settlement.exported', $account->merchant, $settlement);

        return response()->streamDownload(function () use ($settlement) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['settlement_id', 'type', 'amount', 'currency', 'description', 'created_at'], escape: '');

            BalanceTransaction::query()->where('settlement_id', $settlement->id)->chunkById(500, function ($entries) use ($out, $settlement) {
                /** @var BalanceTransaction $entry */
                foreach ($entries as $entry) {
                    fputcsv($out, array_map(ExportPaymentsController::safeCell(...), [
                        $settlement->public_id,
                        $entry->type->value,
                        $entry->amount,
                        $entry->currency,
                        $entry->description,
                        $entry->created_at?->toIso8601String(),
                    ]), escape: '');
                }
            });

            fclose($out);
        }, 'settlement-'.$settlement->public_id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
