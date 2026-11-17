<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CheckoutSession;
use App\Models\Payment;
use App\Support\MarkdownText;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Receipt for payers who left an email address on a payment link.
 */
class PaymentReceipt extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment, public CheckoutSession $session) {}

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

        $message = (new MailMessage)
            ->subject(__('Your receipt from :merchant', ['merchant' => $merchant->name]))
            ->line(__(':merchant received your payment of :amount.', ['merchant' => MarkdownText::escape($merchant->name), 'amount' => Money::format($this->payment->amount, $this->payment->currency)]));

        foreach ($this->session->products as $product) {
            $message->line(MarkdownText::escape((string) $product['name']).' × '.$product['quantity'].' — '.Money::decimal($product['quantity'] * $product['unit_amount']));
        }

        return $message
            ->line(__('Invoice #:invoice · Card :card', ['invoice' => $this->session->invoice, 'card' => $this->payment->masked_card]))
            ->line(__('Keep this email in case you need to contact the merchant.'));
    }
}
