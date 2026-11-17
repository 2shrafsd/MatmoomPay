<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Actions\Webhooks\QueueWebhook;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WebhookEvent;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\CheckoutSession;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;

/**
 * Creates the subscription and its first, already paid, invoice when a subscription checkout is paid.
 * Runs inside the payment finalisation transaction and is safe to call twice.
 */
final readonly class StartSubscription
{
    public function __construct(private QueueWebhook $queueWebhook) {}

    public function handle(CheckoutSession $session, Payment $payment, ?PaymentMethod $paymentMethod): ?Subscription
    {
        if (! $session->isSubscription() || $session->plan_id === null || $session->customer_id === null) {
            return null;
        }

        $existing = Subscription::query()->where('checkout_session_id', $session->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $plan = $session->plan()->firstOrFail();
        $start = now();
        $end = $plan->interval->advance($start, $plan->interval_count);

        $subscription = Subscription::create([
            'merchant_id' => $session->merchant_id,
            'livemode' => $session->livemode,
            'plan_id' => $plan->id,
            'customer_id' => $session->customer_id,
            'payment_method_id' => $paymentMethod->id ?? $payment->payment_method_id,
            'checkout_session_id' => $session->id,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => $start,
            'current_period_end' => $end,
        ]);

        $invoice = SubscriptionInvoice::create([
            'merchant_id' => $session->merchant_id,
            'livemode' => $session->livemode,
            'subscription_id' => $subscription->id,
            'amount_due' => $payment->amount,
            'amount_paid' => $payment->amount,
            'currency' => $payment->currency,
            'status' => InvoiceStatus::Paid,
            'attempt_count' => 1,
            'period_start' => $start,
            'period_end' => $end,
            'paid_at' => now(),
        ]);

        $subscription->load(SubscriptionResource::RELATIONS);
        $invoice->load(InvoiceResource::RELATIONS);

        $this->queueWebhook->handle($subscription->merchant, $subscription->livemode, WebhookEvent::SubscriptionCreated, SubscriptionResource::make($subscription)->resolve());
        $this->queueWebhook->handle($subscription->merchant, $subscription->livemode, WebhookEvent::InvoicePaid, InvoiceResource::make($invoice)->resolve());

        return $subscription;
    }
}
