<?php

declare(strict_types=1);

namespace App\Enums;

enum ChallengeType: string
{
    case Otp = 'otp';
    case ThreeDSecure = '3ds';
}
