<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\MerchantDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public MerchantDocument $document) {}

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
            ->subject(__('Please upload a new :document', ['document' => $this->document->type->label()]))
            ->line(__('Our review team could not accept the :document you uploaded.', ['document' => $this->document->type->label()]))
            ->line(__('Reason: :reason', ['reason' => (string) $this->document->review_note]))
            ->action(__('Upload a new document'), route('portal.business'));
    }
}
