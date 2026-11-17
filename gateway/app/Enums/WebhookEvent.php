<?php

declare(strict_types=1);

namespace App\Enums;

enum WebhookEvent: string
{
    case CheckoutCreated = 'checkout.created';
    case CheckoutCompleted = 'checkout.completed';
    case CheckoutCancelled = 'checkout.cancelled';
    case CheckoutExpired = 'checkout.expired';
    case PaymentIntentSucceeded = 'payment_intent.succeeded';
    case PaymentIntentCancelled = 'payment_intent.cancelled';
    case PaymentPending = 'payment.pending';
    case PaymentSucceeded = 'payment.succeeded';
    case PaymentFailed = 'payment.failed';
    case RefundSucceeded = 'refund.succeeded';
    case RefundFailed = 'refund.failed';
    case SubscriptionCreated = 'subscription.created';
    case SubscriptionPastDue = 'subscription.past_due';
    case SubscriptionCancelled = 'subscription.cancelled';
    case InvoicePaid = 'invoice.paid';
    case InvoicePaymentFailed = 'invoice.payment_failed';
    case SettlementPaid = 'settlement.paid';
    case SettlementFailed = 'settlement.failed';
    case WebhookTest = 'webhook.test';
}
