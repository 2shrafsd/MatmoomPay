<?php

declare(strict_types=1);

namespace App\Actions\PaymentLinks;

use App\Actions\Checkout\CreateCheckoutSession;
use App\Data\CheckoutSessionData;
use App\Exceptions\GatewayException;
use App\Models\CheckoutSession;
use App\Models\PaymentLink;
use App\Support\GatewayAccount;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * Turns a payment link visit into a checkout session. The amount comes from the link when it is fixed,
 * and is bounded by the link's limits when the payer chooses it.
 */
final readonly class StartPaymentLinkCheckout
{
    public function __construct(private CreateCheckoutSession $createCheckoutSession) {}

    /**
     * @param  array{name: string, phone?: ?string, email?: ?string}  $payer
     */
    public function handle(PaymentLink $link, ?int $chosenAmount, array $payer): CheckoutSession
    {
        if (! $link->isAcceptingPayments() || ! $link->merchant->canCollect(false)) {
            throw GatewayException::expired('This payment link is no longer accepting payments.');
        }

        if ($link->livemode && ! $link->merchant->status->canProcessLivePayments()) {
            throw GatewayException::expired('This payment link is no longer accepting payments.');
        }

        $amount = $link->amount ?? $chosenAmount;

        if ($amount === null || $amount < 1) {
            throw GatewayException::invalid('amount', 'Enter the amount you want to pay.');
        }

        if ($amount < Money::minimumAmount()) {
            throw GatewayException::invalid('amount', 'The amount must be at least :amount.');
        }

        if (! $link->hasFixedAmount()) {
            if ($link->min_amount !== null && $amount < $link->min_amount) {
                throw GatewayException::invalid('amount', 'The amount is below the minimum for this link.');
            }

            if ($link->max_amount !== null && $amount > $link->max_amount) {
                throw GatewayException::invalid('amount', 'The amount is above the maximum for this link.');
            }
        }

        $reference = $link->public_id.'-'.Str::lower(Str::random(8));

        return $this->createCheckoutSession->handle(
            new GatewayAccount($link->merchant, $link->livemode),
            new CheckoutSessionData(
                clientReferenceId: $reference,
                products: [['name' => Str::limit($link->title, 40, ''), 'quantity' => 1, 'unit_amount' => $amount]],
                successUrl: route('links.thanks', ['link' => $link, 'reference' => $reference]),
                cancelUrl: route('links.show', $link),
                metadata: array_filter([
                    'Customer name' => $payer['name'],
                    'Customer phone' => $payer['phone'] ?? null,
                    'Customer email' => $payer['email'] ?? null,
                    'payment_link' => $link->public_id,
                ]),
                expireInMinutes: 60,
                paymentLink: $link,
            ),
        );
    }
}
