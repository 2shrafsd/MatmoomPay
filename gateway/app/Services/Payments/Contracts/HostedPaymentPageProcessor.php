<?php

declare(strict_types=1);

namespace App\Services\Payments\Contracts;

use App\Services\Payments\Data\HostedPaymentRedirect;
use App\Services\Payments\Data\HostedPaymentRequest;
use App\Services\Payments\Data\HostedPaymentResult;
use App\Services\Payments\Exceptions\InvalidProcessorCallback;
use Illuminate\Http\Request;

/**
 * An acquirer that collects the card on its own payment page. The cardholder types the card number
 * on the bank's site, so it never reaches our servers (PCI DSS SAQ A for the hosted checkout).
 *
 * To add a bank: implement this interface (plus charge/refund from PaymentProcessor for saved cards
 * and refunds) and register the driver in PaymentProcessorResolver.
 */
interface HostedPaymentPageProcessor extends PaymentProcessor
{
    /**
     * Registers the payment with the bank and returns where to send the cardholder.
     */
    public function startHostedPayment(HostedPaymentRequest $request): HostedPaymentRedirect;

    /**
     * Reads and authenticates the bank's answer when the cardholder comes back (or the bank notifies us).
     *
     * @throws InvalidProcessorCallback when the signature, freshness or format check fails
     */
    public function completeHostedPayment(Request $request): HostedPaymentResult;
}
