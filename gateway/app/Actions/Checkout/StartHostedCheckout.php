<?php

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Exceptions\GatewayException;
use App\Models\CheckoutSession;
use App\Models\HostedPaymentAttempt;
use App\Services\Payments\Contracts\HostedPaymentPageProcessor;
use App\Services\Payments\Data\HostedPaymentRedirect;
use App\Services\Payments\Data\HostedPaymentRequest;
use App\Services\Payments\Exceptions\ProcessorUnavailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Opens a bank-page attempt for a checkout session. The amount is fixed here from the session,
 * so nothing the browser sends can change what the bank is asked to charge.
 */
final class StartHostedCheckout
{
    public function handle(CheckoutSession $session, bool $saveCard, HostedPaymentPageProcessor $processor): HostedPaymentRedirect
    {
        $attempt = DB::transaction(function () use ($session, $saveCard, $processor): HostedPaymentAttempt {
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

            HostedPaymentAttempt::query()
                ->where('checkout_session_id', $locked->id)
                ->where('status', HostedPaymentAttempt::PENDING)
                ->update(['status' => HostedPaymentAttempt::SUPERSEDED]);

            return HostedPaymentAttempt::create([
                'merchant_id' => $locked->merchant_id,
                'livemode' => $locked->livemode,
                'checkout_session_id' => $locked->id,
                'processor' => $processor->name(),
                'amount' => $locked->total_amount,
                'currency' => $locked->currency,
                'save_card' => ($saveCard || $locked->isSubscription()) && $locked->customer_id !== null,
                'status' => HostedPaymentAttempt::PENDING,
                'expires_at' => now()->addMinutes(30),
            ]);
        });

        try {
            return $processor->startHostedPayment(new HostedPaymentRequest(
                reference: $attempt->public_id,
                amount: $attempt->amount,
                currency: $attempt->currency,
                description: Str::limit($session->merchant->name.' #'.$session->invoice, 60, ''),
                returnUrl: route('checkout.return', $attempt),
                cancelUrl: route('checkout.show', $session),
                saveCard: $attempt->save_card,
                locale: app()->getLocale(),
            ));
        } catch (ProcessorUnavailable) {
            throw GatewayException::processorUnavailable();
        }
    }
}
