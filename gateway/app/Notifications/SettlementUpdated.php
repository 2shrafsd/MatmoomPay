<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\SettlementStatus;
use App\Models\Settlement;
use App\Support\MarkdownText;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells finance and owners that a payout was transferred, or that the bank returned it.
 */
class SettlementUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Settlement $settlement) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = Money::format($this->settlement->net_amount, $this->settlement->currency);
        $message = new MailMessage;

        if ($this->settlement->status === SettlementStatus::Failed) {
            $message->subject(__('Your settlement of :amount was returned', ['amount' => $amount]))
                ->line(__('The bank returned the transfer of :amount. The balance goes into your next settlement.', ['amount' => $amount]))
                ->line(__('Reason: :reason', ['reason' => MarkdownText::escape($this->settlement->failure_reason)]))
                ->line(__('Check your bank account details in the business profile.'));
        } else {
            $message->subject(__('We transferred :amount to your bank', ['amount' => $amount]))
                ->line(__(':amount is on its way to :bank, account ending in :last4.', ['amount' => $amount, 'bank' => MarkdownText::escape($this->settlement->bank_name), 'last4' => substr((string) $this->settlement->iban, -4)]))
                ->line(__('Bank reference: :reference', ['reference' => MarkdownText::escape($this->settlement->bank_reference)]));
        }

        return $message->action(__('View the settlement'), route('portal.settlements.show', $this->settlement->public_id));
    }
}
