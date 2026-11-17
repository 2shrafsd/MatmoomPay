<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\WebhookDelivery;
use App\Support\MarkdownText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells developers that an event could not be delivered after every retry, at most once a day per endpoint.
 */
class WebhookEndpointFailing extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WebhookDelivery $delivery) {}

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
            ->subject(__('Your webhook endpoint is not answering'))
            ->line(__('We could not deliver :event to :url after every retry.', ['event' => $this->delivery->event_type, 'url' => MarkdownText::escape($this->delivery->endpoint?->url)]))
            ->line(__('Last error: :error', ['error' => MarkdownText::escape($this->delivery->last_error ?? '—')]))
            ->line(__('Fix the endpoint, then resend the failed events from the webhooks page.'))
            ->action(__('Open webhooks'), route('portal.webhooks'));
    }
}
