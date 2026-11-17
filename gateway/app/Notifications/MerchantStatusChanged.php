<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\MerchantStatus;
use App\Models\Merchant;
use App\Support\MarkdownText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MerchantStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Merchant $merchant, public MerchantStatus $status, public ?string $reason) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject(match ($this->status) {
            MerchantStatus::Active => __('Live payments are now open for :merchant', ['merchant' => $this->merchant->name]),
            MerchantStatus::Rejected => __('Update on your :app application', ['app' => config('app.name')]),
            MerchantStatus::Suspended => __('Payments for :merchant are paused', ['merchant' => $this->merchant->name]),
            MerchantStatus::Pending => __('Your account is under review'),
            MerchantStatus::Banned => __(':merchant is closed on :app', ['merchant' => $this->merchant->name, 'app' => config('app.name')]),
        });

        $message = match ($this->status) {
            MerchantStatus::Active => $message
                ->line(__('Good news: we approved :merchant. You can now create live keys and accept real card payments.', ['merchant' => MarkdownText::escape($this->merchant->name)]))
                ->action(__('Open the dashboard'), route('portal.developers')),
            MerchantStatus::Rejected => $message
                ->line(__('We could not approve :merchant at this time.', ['merchant' => MarkdownText::escape($this->merchant->name)])),
            MerchantStatus::Suspended => $message
                ->line(__('We paused payments for :merchant. Live keys were revoked.', ['merchant' => MarkdownText::escape($this->merchant->name)])),
            MerchantStatus::Pending => $message,
            MerchantStatus::Banned => $message
                ->line(__('We closed :merchant. Payments, API keys and the dashboard are no longer available.', ['merchant' => MarkdownText::escape($this->merchant->name)])),
        };

        if ($this->reason !== null && $this->reason !== '') {
            $message->line(__('Reason: :reason', ['reason' => MarkdownText::escape($this->reason)]));
        }

        return $message->line(__('Reply to this email if you have questions.'));
    }
}
