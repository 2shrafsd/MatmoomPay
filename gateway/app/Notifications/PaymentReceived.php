<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Payment;
use App\Support\MarkdownText;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the team about a successful live payment (off by default; each person can switch it on).
 */
class PaymentReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = Money::format($this->payment->amount, $this->payment->currency);

        return (new MailMessage)
            ->subject(__('New payment of :amount', ['amount' => $amount]))
            ->line(__(':merchant received a payment of :amount.', ['merchant' => MarkdownText::escape($this->payment->merchant->name), 'amount' => $amount]))
            ->line(__('Payment :id · card ending in :last4', ['id' => $this->payment->public_id, 'last4' => $this->payment->card_last_four]))
            ->action(__('View the payment'), route('portal.payments.show', $this->payment));
    }
}
