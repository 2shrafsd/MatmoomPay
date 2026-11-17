<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\Webhooks\QueueWebhook;
use App\Enums\PaymentStatus;
use App\Enums\WebhookEvent;
use App\Http\Resources\PaymentResource;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\Payments\Data\CardToken;
use App\Support\PublicId;

final readonly class CreatePendingPayment
{
    public function __construct(private QueueWebhook $queueWebhook) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Merchant $merchant, bool $livemode, int $amount, CardToken|PaymentMethod $card, string $processor, array $attributes = []): Payment
    {
        $payment = Payment::create([
            'public_id' => $this->uniquePaymentId(),
            'merchant_id' => $merchant->id,
            'livemode' => $livemode,
            'amount' => $amount,
            'currency' => (string) config('gateway.currency'),
            'status' => PaymentStatus::InProcess,
            'processor' => $processor,
            ...$this->cardAttributes($card),
            ...$attributes,
        ]);

        $payment->load(PaymentResource::RELATIONS);

        $this->queueWebhook->handle($merchant, $livemode, WebhookEvent::PaymentPending, PaymentResource::make($payment)->resolve());

        return $payment;
    }

    /**
     * @return array<string, mixed>
     */
    private function cardAttributes(CardToken|PaymentMethod $card): array
    {
        if ($card instanceof PaymentMethod) {
            return [
                'payment_method_id' => $card->id,
                'customer_id' => $card->customer_id,
                'masked_card' => substr($card->bin, 0, 4).' '.substr($card->bin, 4, 2).'XX XXXX '.$card->last_four,
                'card_bin' => $card->bin,
                'card_last_four' => $card->last_four,
                'card_brand' => $card->brand,
                'card_type' => $card->card_type,
                'card_fingerprint' => $card->fingerprint,
                'card_expiry_month' => $card->expiry_month,
                'card_expiry_year' => $card->expiry_year,
                'card_token' => $card->processor_token,
            ];
        }

        return [
            'masked_card' => $card->maskedCard(),
            'card_bin' => $card->bin,
            'card_last_four' => $card->lastFour,
            'card_brand' => $card->brand,
            'card_type' => $card->type,
            'card_fingerprint' => $card->fingerprint,
            'card_expiry_month' => $card->expiryMonth,
            'card_expiry_year' => $card->expiryYear,
            'card_token' => $card->token,
        ];
    }

    private function uniquePaymentId(): string
    {
        do {
            $id = PublicId::numeric(6);
        } while (Payment::query()->where('public_id', $id)->exists());

        return $id;
    }
}
