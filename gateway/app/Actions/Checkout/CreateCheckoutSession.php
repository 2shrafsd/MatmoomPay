<?php

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Actions\Webhooks\QueueWebhook;
use App\Data\CheckoutSessionData;
use App\Enums\CheckoutPaymentStatus;
use App\Enums\WebhookEvent;
use App\Exceptions\GatewayException;
use App\Http\Resources\CheckoutSessionResource;
use App\Models\CheckoutSession;
use App\Support\GatewayAccount;
use App\Support\Money;
use App\Support\PublicId;
use Illuminate\Support\Facades\DB;

final readonly class CreateCheckoutSession
{
    public function __construct(private QueueWebhook $queueWebhook) {}

    public function handle(GatewayAccount $account, CheckoutSessionData $data): CheckoutSession
    {
        if ($data->plan !== null && $data->customer === null) {
            throw GatewayException::invalid('customer_id', 'A customer is required for subscription sessions.');
        }

        $products = $data->plan === null
            ? $data->products
            : [['name' => mb_substr($data->plan->name, 0, 40), 'quantity' => 1, 'unit_amount' => $data->plan->amount]];
        $total = array_sum(array_map(fn (array $product): int => $product['quantity'] * $product['unit_amount'], $products));

        if ($total < Money::minimumAmount()) {
            throw GatewayException::invalid('products', Money::belowMinimumMessage());
        }

        if ($total > (int) config('gateway.limits.max_total_amount')) {
            throw GatewayException::invalid('products', 'The total amount exceeds the maximum allowed for one session.');
        }

        $expiresIn = $data->expireInMinutes ?? (int) config('gateway.checkout.default_expiry_minutes');

        return DB::transaction(function () use ($account, $data, $products, $total, $expiresIn): CheckoutSession {
            $session = CheckoutSession::create([
                'merchant_id' => $account->merchant->id,
                'livemode' => $account->livemode,
                'customer_id' => $data->customer?->id,
                'payment_link_id' => $data->paymentLink?->id,
                'plan_id' => $data->plan?->id,
                'client_reference_id' => $data->clientReferenceId,
                'mode' => $data->mode(),
                'products' => $products,
                'total_amount' => $total,
                'currency' => (string) config('gateway.currency'),
                'success_url' => $data->successUrl,
                'cancel_url' => $data->cancelUrl,
                'payment_status' => CheckoutPaymentStatus::Unpaid,
                'invoice' => $this->uniqueInvoiceNumber(),
                'save_card_on_success' => $data->saveCardOnSuccess || $data->plan !== null,
                'metadata' => $data->metadata,
                'expire_at' => now()->addMinutes($expiresIn),
            ]);

            $session->setRelation('customer', $data->customer)->setRelation('plan', $data->plan);

            $this->queueWebhook->handle(
                $account->merchant,
                $account->livemode,
                WebhookEvent::CheckoutCreated,
                CheckoutSessionResource::make($session)->resolve(),
            );

            return $session;
        });
    }

    private function uniqueInvoiceNumber(): string
    {
        do {
            $invoice = PublicId::numeric(4);
        } while (CheckoutSession::query()->where('invoice', $invoice)->exists());

        return $invoice;
    }
}
