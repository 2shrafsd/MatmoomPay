<?php

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Actions\Webhooks\QueueWebhook;
use App\Enums\CheckoutPaymentStatus;
use App\Enums\PaymentStatus;
use App\Enums\WebhookEvent;
use App\Http\Resources\CheckoutSessionResource;
use App\Models\CheckoutSession;
use Illuminate\Support\Facades\DB;

/**
 * Closes an unpaid session whose time ran out and tells the merchant, so their order stops waiting.
 * A session with a charge still in flight is left for reconciliation to settle first.
 */
final readonly class ExpireCheckoutSession
{
    public function __construct(private QueueWebhook $queueWebhook) {}

    public function handle(CheckoutSession $session): bool
    {
        return DB::transaction(function () use ($session): bool {
            $session = CheckoutSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($session->payment_status !== CheckoutPaymentStatus::Unpaid || ! $session->isExpired()) {
                return false;
            }

            if ($session->payments()->where('status', PaymentStatus::InProcess)->exists()) {
                return false;
            }

            $session->update(['payment_status' => CheckoutPaymentStatus::Expired]);
            $session->load(CheckoutSessionResource::RELATIONS);

            $this->queueWebhook->handle($session->merchant, $session->livemode, WebhookEvent::CheckoutExpired, CheckoutSessionResource::make($session)->resolve());

            return true;
        });
    }
}
