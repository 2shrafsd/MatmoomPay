<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Merchant;
use App\Models\User;
use App\Support\MarkdownText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells every owner and admin that payouts now go to a different bank account.
 */
class SettlementAccountChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Merchant $merchant, public User $changedBy) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('The settlement account of :merchant was changed', ['merchant' => $this->merchant->name]))
            ->line(__(':name changed the bank account that receives payouts for :merchant.', ['name' => MarkdownText::escape($this->changedBy->name), 'merchant' => MarkdownText::escape($this->merchant->name)]))
            ->line(__('New account: :bank, ending in :last4.', ['bank' => MarkdownText::escape($this->merchant->settlement_bank_name), 'last4' => substr((string) $this->merchant->settlement_iban, -4)]))
            ->line(__('Payouts are paused until our team verifies the new account.'))
            ->action(__('Review the business profile'), route('portal.business'))
            ->line(__('If you did not expect this change, reply to this email right away.'));
    }
}
