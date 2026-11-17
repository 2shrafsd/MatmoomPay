<?php

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Actions\Payments\FailPayment;
use App\Actions\Webhooks\QueueWebhook;
use App\Enums\CheckoutPaymentStatus;
use App\Enums\WebhookEvent;
use App\Exceptions\GatewayException;
use App\Http\Resources\CheckoutSessionResource;
use App\Models\CheckoutSession;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

final readonly class CancelCheckoutSession
{
    public function __construct(
        private QueueWebhook $queueWebhook,
        private FailPayment $failPayment,
    ) {}

    public function handle(CheckoutSession $session): CheckoutSession
    {
        return DB::transaction(function () use ($session): CheckoutSession {
            $session = CheckoutSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($session->payment_status !== CheckoutPaymentStatus::Unpaid) {
                throw GatewayException::cannotUpdate('Only unpaid sessions can be cancelled.');
            }

            if ($session->payments()->beingCharged()->exists()) {
                throw GatewayException::paymentInProgress();
            }

            $session->update([
                'payment_status' => CheckoutPaymentStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            $session->payments()->awaitingChallenge()->get()
                ->each(fn (Payment $payment) => $this->failPayment->handle($payment, 'The checkout session was cancelled.'));

            $session->load(CheckoutSessionResource::RELATIONS);

            $this->queueWebhook->handle(
                $session->merchant,
                $session->livemode,
                WebhookEvent::CheckoutCancelled,
                CheckoutSessionResource::make($session)->resolve(),
            );

            return $session;
        });
    }
}
