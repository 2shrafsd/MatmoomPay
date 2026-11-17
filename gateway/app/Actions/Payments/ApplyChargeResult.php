<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Services\Payments\Data\ChargeOutcome;
use App\Services\Payments\Data\ChargeResult;

/**
 * Moves a pending payment to its next state from a processor answer.
 */
final readonly class ApplyChargeResult
{
    public function __construct(
        private FinalizeSuccessfulPayment $finalize,
        private FailPayment $fail,
    ) {}

    public function handle(Payment $payment, ChargeResult $result): Payment
    {
        $payment->processor_reference = $result->processorReference;

        return match ($result->outcome) {
            ChargeOutcome::Succeeded => $this->finalize->handle(tap($payment)->save()),
            ChargeOutcome::Declined => $this->fail->handle($payment, $result->declineReason ?? 'Declined by the issuing bank.'),
            ChargeOutcome::RequiresChallenge => tap($payment, fn (Payment $payment) => $payment->fill([
                'challenge_type' => $result->challengeType,
                'challenge_attempts' => 0,
            ])->save()),
            ChargeOutcome::IncorrectChallengeCode => $this->recordIncorrectCode($payment),
        };
    }

    private function recordIncorrectCode(Payment $payment): Payment
    {
        $payment->challenge_attempts++;

        if ($payment->challenge_attempts >= (int) config('gateway.checkout.max_challenge_attempts')) {
            return $this->fail->handle($payment, 'Too many incorrect verification codes.');
        }

        $payment->save();

        return $payment;
    }
}
