<?php

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Actions\Payments\CreatePendingPayment;
use App\Actions\Payments\FailPayment;
use App\Exceptions\GatewayException;
use App\Models\CheckoutSession;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\Payments\Data\CardToken;
use Illuminate\Support\Facades\DB;

/**
 * Locks the session, retires any unfinished attempt and opens a new pending payment, so one
 * session can never be charged twice even when the payer double-submits.
 */
final readonly class OpenCheckoutPayment
{
    public function __construct(
        private CreatePendingPayment $createPendingPayment,
        private FailPayment $failPayment,
    ) {}

    public function handle(CheckoutSession $session, CardToken|PaymentMethod $card, string $processor, bool $saveCard, ?string $ip): Payment
    {
        return DB::transaction(function () use ($session, $card, $processor, $saveCard, $ip): Payment {
            $locked = CheckoutSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if (! $locked->canTakePayment()) {
                throw GatewayException::cannotUpdate('This checkout session can no longer be paid.');
            }

            if ($locked->payment_link_id !== null && ! $locked->paymentLink?->isAcceptingPayments()) {
                throw GatewayException::cannotUpdate('This payment link is no longer accepting payments.');
            }

            if ($locked->payments()->beingCharged()->exists()) {
                throw GatewayException::paymentInProgress();
            }

            // A payment still waiting for its OTP never reached capture, so a fresh attempt may replace it.
            $locked->payments()->awaitingChallenge()->get()
                ->each(fn (Payment $stale) => $this->failPayment->handle($stale, 'Replaced by a newer payment attempt.'));

            return $this->createPendingPayment->handle(
                merchant: $locked->merchant,
                livemode: $locked->livemode,
                amount: $locked->total_amount,
                card: $card,
                processor: $processor,
                attributes: [
                    'checkout_session_id' => $locked->id,
                    'customer_id' => $locked->customer_id,
                    'save_card' => ($saveCard || $locked->isSubscription()) && $locked->customer_id !== null && ! $card instanceof PaymentMethod,
                    'ip_address' => $ip,
                ],
            );
        });
    }
}
