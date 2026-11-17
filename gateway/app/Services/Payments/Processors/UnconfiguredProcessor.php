<?php

declare(strict_types=1);

namespace App\Services\Payments\Processors;

use App\Enums\ChallengeType;
use App\Services\Payments\Contracts\PaymentProcessor;
use App\Services\Payments\Data\CardDetails;
use App\Services\Payments\Data\CardToken;
use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Data\ChargeResult;
use App\Services\Payments\Data\RefundResult;
use App\Services\Payments\Exceptions\ProcessorUnavailable;

/**
 * Placeholder for live mode until an acquiring bank driver is registered. Refuses every operation.
 */
final class UnconfiguredProcessor implements PaymentProcessor
{
    public function name(): string
    {
        return 'unconfigured';
    }

    public function tokenize(CardDetails $card): CardToken
    {
        throw ProcessorUnavailable::notConfigured();
    }

    public function charge(ChargeRequest $request): ChargeResult
    {
        throw ProcessorUnavailable::notConfigured();
    }

    public function completeChallenge(string $token, string $processorReference, ChallengeType $type, string $code): ChargeResult
    {
        throw ProcessorUnavailable::notConfigured();
    }

    public function refund(string $processorReference, int $amount, string $currency, string $reference): RefundResult
    {
        throw ProcessorUnavailable::notConfigured();
    }

    public function retrieve(string $reference): ?ChargeResult
    {
        throw ProcessorUnavailable::notConfigured();
    }
}
