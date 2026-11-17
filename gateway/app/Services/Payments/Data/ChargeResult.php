<?php

declare(strict_types=1);

namespace App\Services\Payments\Data;

use App\Enums\ChallengeType;

final readonly class ChargeResult
{
    public function __construct(
        public ChargeOutcome $outcome,
        public string $processorReference,
        public ?ChallengeType $challengeType = null,
        public ?string $declineReason = null,
    ) {}

    public static function succeeded(string $reference): self
    {
        return new self(ChargeOutcome::Succeeded, $reference);
    }

    public static function declined(string $reference, string $reason): self
    {
        return new self(ChargeOutcome::Declined, $reference, declineReason: $reason);
    }

    public static function challenge(string $reference, ChallengeType $type): self
    {
        return new self(ChargeOutcome::RequiresChallenge, $reference, $type);
    }

    public static function incorrectCode(string $reference, ChallengeType $type): self
    {
        return new self(ChargeOutcome::IncorrectChallengeCode, $reference, $type);
    }
}
