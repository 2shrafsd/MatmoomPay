<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Payment;
use App\Queries\PaymentSearch;
use App\Support\GatewayAccount;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the filtered transactions of the current merchant and mode as CSV.
 */
class ExportPaymentsController
{
    public function __invoke(Request $request, GatewayAccount $account, RecordAuditLog $recordAuditLog): StreamedResponse
    {
        $query = PaymentSearch::fromInput($account, $request->query())->query();

        $recordAuditLog->handle($request->user(), 'payments.exported', $account->merchant, null, ['livemode' => $account->livemode]);

        $filename = 'transactions-'.($account->livemode ? 'live' : 'test').'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['payment_id', 'created_at', 'status', 'amount', 'fee', 'refunded', 'net', 'currency', 'card', 'card_type', 'invoice', 'reference', 'failure_reason'], escape: '');

            $query->chunkById(500, function ($payments) use ($out) {
                /** @var Payment $payment */
                foreach ($payments as $payment) {
                    fputcsv($out, array_map(self::safeCell(...), [
                        $payment->public_id,
                        $payment->created_at?->toIso8601String(),
                        $payment->status->value,
                        $payment->amount,
                        $payment->fee,
                        $payment->refunded_amount,
                        $payment->amount - $payment->fee - $payment->refunded_amount,
                        $payment->currency,
                        $payment->card_brand->value.' '.$payment->card_last_four,
                        $payment->card_type->value,
                        $payment->checkoutSession?->invoice,
                        $payment->checkoutSession->client_reference_id ?? $payment->paymentIntent->client_reference_id ?? null,
                        $payment->failure_reason,
                    ]), escape: '');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Neutralises spreadsheet formula injection: merchant references and metadata come from API
     * callers, so a value such as "=HYPERLINK(...)" must open as text, not as a formula.
     */
    public static function safeCell(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
