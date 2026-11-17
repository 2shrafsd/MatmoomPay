<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\Webhooks\QueueWebhook;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentStatus;
use App\Enums\WebhookEvent;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\PaymentIntent;
use Illuminate\Support\Facades\DB;

final readonly class FailPayment
{
    public function __construct(private QueueWebhook $queueWebhook) {}

    public function handle(Payment $payment, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $reason): Payment {
            if ($payment->status !== PaymentStatus::InProcess) {
                return $payment;
            }

            $payment->fill([
                'status' => PaymentStatus::Failed,
                'failure_reason' => $reason,
                'card_token' => null,
                'completed_at' => now(),
            ])->save();

            if ($payment->payment_intent_id !== null) {
                PaymentIntent::query()
                    ->whereKey($payment->payment_intent_id)
                    ->whereNotIn('status', [PaymentIntentStatus::Cancelled, PaymentIntentStatus::Succeeded])
                    ->update(['status' => PaymentIntentStatus::RequiresPaymentMethod, 'next_action' => null]);
            }

            $payment->load(PaymentResource::RELATIONS);

            $this->queueWebhook->handle($payment->merchant, $payment->livemode, WebhookEvent::PaymentFailed, PaymentResource::make($payment)->resolve());

            return $payment;
        });
    }
}
