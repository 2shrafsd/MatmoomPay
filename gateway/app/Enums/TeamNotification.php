<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Emails a team member can turn on or off in their profile.
 */
enum TeamNotification: string
{
    case PaymentReceived = 'payment_received';
    case RefundProcessed = 'refund_processed';
    case SettlementUpdated = 'settlement_updated';
    case RenewalFailed = 'renewal_failed';
    case WebhookFailing = 'webhook_failing';

    public function label(): string
    {
        return match ($this) {
            self::PaymentReceived => __('Every successful live payment'),
            self::RefundProcessed => __('Refunds on live payments'),
            self::SettlementUpdated => __('Settlements transferred or returned by the bank'),
            self::RenewalFailed => __('Failed subscription renewals'),
            self::WebhookFailing => __('A webhook that keeps failing'),
        };
    }

    /**
     * One email per payment can be a lot, so it starts off; everything that needs action starts on.
     */
    public function enabledByDefault(): bool
    {
        return $this !== self::PaymentReceived;
    }

    /**
     * Who on the team receives this email at all, before personal preferences.
     *
     * @return list<MerchantRole>
     */
    public function roles(): array
    {
        return match ($this) {
            self::WebhookFailing => [MerchantRole::Owner, MerchantRole::Admin, MerchantRole::Developer],
            self::RefundProcessed, self::SettlementUpdated => [MerchantRole::Owner, MerchantRole::Admin, MerchantRole::Finance],
            default => [MerchantRole::Owner, MerchantRole::Admin],
        };
    }
}
