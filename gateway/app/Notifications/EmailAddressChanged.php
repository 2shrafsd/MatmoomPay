<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Support\MarkdownText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Warns the previous address that the account's sign-in and recovery email was changed.
 */
class EmailAddressChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $newEmail) {}

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
            ->subject(__('Your account email was changed'))
            ->line(__('The email address on your account was changed to :email.', ['email' => MarkdownText::escape($this->newEmail)]))
            ->line(__('If you did not make this change, reply to this email right away so we can secure your account.'));
    }
}
