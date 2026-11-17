<?php

declare(strict_types=1);

namespace App\Actions\PaymentIntents;

use App\Actions\Payments\FailPayment;
use App\Actions\Webhooks\QueueWebhook;
use App\Enums\PaymentIntentStatus;
use App\Enums\WebhookEvent;
use App\Exceptions\GatewayException;
use App\Http\Resources\PaymentIntentResource;
use App\Models\Payment;
use App\Models\PaymentIntent;
use Illuminate\Support\Facades\DB;

final readonly class CancelPaymentIntent
{
    public function __construct(
        private FailPayment $failPayment,
        private QueueWebhook $queueWebhook,
    ) {}

    public function handle(PaymentIntent $intent): PaymentIntent
    {
        return DB::transaction(function () use ($intent): PaymentIntent {
            $intent = PaymentIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();

            if ($intent->status->isFinal()) {
                throw GatewayException::cannotUpdate('This payment intent is already '.$intent->status->value.'.');
            }

            if ($intent->payments()->beingCharged()->exists()) {
                throw GatewayException::paymentInProgress();
            }

            $intent->update([
                'status' => PaymentIntentStatus::Cancelled,
                'next_action' => null,
                'cancelled_at' => now(),
            ]);

            $intent->payments()->awaitingChallenge()->get()
                ->each(fn (Payment $payment) => $this->failPayment->handle($payment, 'The payment intent was cancelled.'));

            $intent->load(PaymentIntentResource::RELATIONS);

            $this->queueWebhook->handle($intent->merchant, $intent->livemode, WebhookEvent::PaymentIntentCancelled, PaymentIntentResource::make($intent)->resolve());

            return $intent;
        });
    }
}
