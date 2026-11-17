<?php

declare(strict_types=1);

namespace App\Services\Payments\Contracts;

use App\Enums\ChallengeType;
use App\Services\Payments\Data\CardDetails;
use App\Services\Payments\Data\CardToken;
use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Data\ChargeResult;
use App\Services\Payments\Data\RefundResult;

/**
 * The boundary between the gateway and the acquiring bank. Everything above this interface
 * works with opaque tokens; only a driver ever sees card data, and it must not persist it.
 */
interface PaymentProcessor
{
    public function name(): string;

    /**
     * Exchanges raw card data for a reusable processor token.
     */
    public function tokenize(CardDetails $card): CardToken;

    public function charge(ChargeRequest $request): ChargeResult;

    /**
     * Submits the cardholder's OTP or 3-D Secure response for a charge that required a challenge.
     */
    public function completeChallenge(string $token, string $processorReference, ChallengeType $type, string $code): ChargeResult;

    /**
     * Refunds part or all of a captured charge. $reference is our refund ID and must be passed to the bank
     * as its idempotency key, so retrying after a timeout never refunds twice.
     */
    public function refund(string $processorReference, int $amount, string $currency, string $reference): RefundResult;

    /**
     * Looks up a charge by our payment reference when its outcome is unknown (for example after a timeout).
     * Returns null when the bank never received it.
     */
    public function retrieve(string $reference): ?ChargeResult;
}
