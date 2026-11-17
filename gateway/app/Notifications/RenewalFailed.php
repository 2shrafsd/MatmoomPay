<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SubscriptionInvoice;
use App\Support\MarkdownText;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells owners that a live renewal did not go through, so they can contact the customer.
 */
class RenewalFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SubscriptionInvoice $invoice, public string $reason) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = Money::format($this->invoice->amount_due, $this->invoice->currency);
        // Notifications render in the recipient's locale, and Carbon follows the app locale.
        $retry = $this->invoice->next_payment_attempt?->translatedFormat('j F');

        return (new MailMessage)
            ->subject(__('A subscription renewal of :amount failed', ['amount' => $amount]))
            ->line(__('The renewal for subscription :subscription was not paid: :reason', ['subscription' => $this->invoice->subscription->public_id, 'reason' => MarkdownText::escape(__($this->reason))]))
            ->line($retry !== null ? __('We will try again on :date.', ['date' => $retry]) : __('There are no retries left, so the subscription was cancelled.'))
            ->action(__('View subscriptions'), route('portal.subscriptions'));
    }
}
