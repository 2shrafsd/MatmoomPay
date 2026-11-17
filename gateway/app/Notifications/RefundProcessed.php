<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Refund;
use App\Support\MarkdownText;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells finance and owners that a live refund went through.
 */
class RefundProcessed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Refund $refund) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = Money::format($this->refund->amount, $this->refund->payment->currency);

        return (new MailMessage)
            ->subject(__('Refund of :amount sent', ['amount' => $amount]))
            ->line(__(':amount was refunded for payment :payment.', ['amount' => $amount, 'payment' => $this->refund->payment->public_id]))
            ->line(__('Reason: :reason', ['reason' => MarkdownText::escape($this->refund->reason)]))
            ->line(__('The amount is deducted from your next settlement.'))
            ->action(__('View the payment'), route('portal.payments.show', $this->refund->payment));
    }
}
