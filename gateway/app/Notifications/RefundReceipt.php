<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CheckoutSession;
use App\Models\Refund;
use App\Support\MarkdownText;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the payer their money is on its way back. Banks usually take a few working days to show it.
 */
class RefundReceipt extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Refund $refund, public CheckoutSession $session) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $merchant = $this->session->merchant;
        $amount = Money::format($this->refund->amount, $this->refund->payment->currency);

        return (new MailMessage)
            ->subject(__(':merchant refunded :amount', ['merchant' => $merchant->name, 'amount' => $amount]))
            ->line(__(':merchant refunded :amount to your card ending in :last4.', ['merchant' => MarkdownText::escape($merchant->name), 'amount' => $amount, 'last4' => $this->refund->payment->card_last_four]))
            ->line(__('Your bank usually shows the refund within a few working days.'))
            ->line(__('Invoice #:invoice · Refund :refund', ['invoice' => $this->session->invoice, 'refund' => $this->refund->public_id]));
    }
}
