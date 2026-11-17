<?php

declare(strict_types=1);

namespace App\Services\Payments\Data;

enum ChargeOutcome: string
{
    case Succeeded = 'succeeded';
    case Declined = 'declined';
    case RequiresChallenge = 'requires_challenge';
    case IncorrectChallengeCode = 'incorrect_challenge_code';
}
