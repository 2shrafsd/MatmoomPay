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

class TeamInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Merchant $merchant,
        public User $inviter,
        #[\SensitiveParameter] public string $token,
    ) {}

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
            ->subject(__('You are invited to join :merchant on :app', ['merchant' => $this->merchant->name, 'app' => config('app.name')]))
            ->line(__(':name invited you to the :merchant team.', ['name' => MarkdownText::escape($this->inviter->name), 'merchant' => MarkdownText::escape($this->merchant->name)]))
            ->line(__('Sign in or create an account with this email address, then accept the invitation.'))
            ->action(__('Accept invitation'), route('invitations.accept', $this->token))
            ->line(__('The invitation expires in 7 days.'));
    }
}
