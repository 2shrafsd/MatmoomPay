<?php

declare(strict_types=1);

namespace App\Actions\PaymentIntents;

use App\Actions\Payments\ChargePayment;
use App\Actions\Payments\CreatePendingPayment;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\GatewayException;
use App\Http\Resources\PaymentIntentResource;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Charges a saved card off-session. When the issuer asks for authentication the intent moves to
 * requires_action and next_action.url points at the hosted verification page.
 */
final readonly class ConfirmPaymentIntent
{
    public function __construct(
        private CreatePendingPayment $createPendingPayment,
        private ChargePayment $chargePayment,
    ) {}

    public function handle(PaymentIntent $intent, ?PaymentMethod $paymentMethod = null): PaymentIntent
    {
        $payment = DB::transaction(function () use ($intent, $paymentMethod): Payment {
            $intent = PaymentIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();

            if (! in_array($intent->status, [PaymentIntentStatus::RequiresConfirmation, PaymentIntentStatus::RequiresPaymentMethod], true)) {
                throw GatewayException::cannotUpdate('This payment intent cannot be confirmed in its current status.');
            }

            if (! $intent->merchant->canCollect($intent->livemode)) {
                throw GatewayException::cannotUpdate('This merchant account cannot take payments right now.');
            }

            if ($intent->payments()->where('status', PaymentStatus::InProcess)->exists()) {
                throw GatewayException::paymentInProgress();
            }

            if ($intent->isExpired()) {
                throw GatewayException::expired('This payment intent has expired.');
            }

            $card = $paymentMethod ?? $intent->paymentMethod;

            if ($card === null) {
                throw GatewayException::invalid('payment_method_id', 'A payment method is required to confirm this payment intent.');
            }

            if ($card->isExpired()) {
                throw GatewayException::paymentRejected('This card has expired.');
            }

            $intent->update([
                'payment_method_id' => $card->id,
                'customer_id' => $card->customer_id,
                'status' => PaymentIntentStatus::RequiresConfirmation,
            ]);

            return $this->createPendingPayment->handle(
                merchant: $intent->merchant,
                livemode: $intent->livemode,
                amount: $intent->amount,
                card: $card,
                processor: $card->processor,
                attributes: ['payment_intent_id' => $intent->id],
            );
        });

        $payment = $this->chargePayment->handle($payment, customerPresent: false);

        if ($payment->status === PaymentStatus::InProcess && $payment->challenge_type !== null) {
            PaymentIntent::query()->whereKey($intent->id)->update([
                'status' => PaymentIntentStatus::RequiresAction,
                'next_action' => json_encode([
                    'url' => URL::temporarySignedRoute('authenticate.show', now()->addMinutes(30), ['payment' => $payment->public_id]),
                    'return_url' => $intent->return_url,
                ], JSON_UNESCAPED_SLASHES),
            ]);
        }

        return $intent->refresh()->load(PaymentIntentResource::RELATIONS);
    }
}
