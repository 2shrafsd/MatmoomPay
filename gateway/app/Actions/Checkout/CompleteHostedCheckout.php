<?php

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Actions\Payments\ApplyChargeResult;
use App\Actions\Payments\CreatePendingPayment;
use App\Models\CheckoutSession;
use App\Models\HostedPaymentAttempt;
use App\Models\Payment;
use App\Services\Payments\Data\ChargeResult;
use App\Services\Payments\Data\HostedPaymentResult;
use App\Services\Payments\Exceptions\InvalidProcessorCallback;
use Illuminate\Support\Facades\DB;

/**
 * Records the bank's authenticated answer for an attempt. Safe against replays (an attempt completes
 * once), tampering (reference, amount and currency must match what we asked for) and races (if
 * another attempt already paid the session, an approved duplicate is refunded straight away).
 */
final readonly class CompleteHostedCheckout
{
    public function __construct(
        private CreatePendingPayment $createPendingPayment,
        private ApplyChargeResult $applyChargeResult,
    ) {}

    public function handle(HostedPaymentAttempt $attempt, HostedPaymentResult $result): ?Payment
    {
        $payment = DB::transaction(function () use ($attempt, $result): ?Payment {
            $attempt = HostedPaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($attempt->status === HostedPaymentAttempt::COMPLETED) {
                return $attempt->payment;
            }

            if (! hash_equals($attempt->public_id, $result->reference) || $result->amount !== $attempt->amount || $result->currency !== $attempt->currency) {
                throw new InvalidProcessorCallback('The bank answer does not match this payment.');
            }

            $session = CheckoutSession::query()->whereKey($attempt->checkout_session_id)->lockForUpdate()->firstOrFail();

            if ($result->card === null) {
                $attempt->update(['status' => HostedPaymentAttempt::COMPLETED]);

                return null;
            }

            $payment = $this->createPendingPayment->handle(
                merchant: $session->merchant,
                livemode: $session->livemode,
                amount: $attempt->amount,
                card: $result->card,
                processor: $attempt->processor,
                attributes: [
                    'checkout_session_id' => $session->id,
                    'customer_id' => $session->customer_id,
                    'save_card' => $attempt->save_card && $result->card->token !== '',
                    'ip_address' => request()->ip(),
                ],
            );

            $attempt->update(['status' => HostedPaymentAttempt::COMPLETED, 'payment_id' => $payment->id]);

            $outcome = $result->approved
                ? ChargeResult::succeeded($result->processorReference)
                : ChargeResult::declined($result->processorReference, $result->declineReason ?? 'Declined by the issuing bank.');

            return $this->applyChargeResult->handle($payment, $outcome);
        });

        return $payment?->refresh();
    }
}
