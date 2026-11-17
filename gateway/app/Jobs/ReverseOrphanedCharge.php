<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Payments\PaymentProcessorResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Reverses money the bank captured for a payment we had already recorded as failed. The payment stays
 * failed for the merchant; the cardholder simply gets the money back.
 */
class ReverseOrphanedCharge implements ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900, 1800, 3600, 7200, 21600];

    public function __construct(public Payment $payment) {}

    public function handle(PaymentProcessorResolver $processors): void
    {
        $result = $processors->forPayment($this->payment)->refund(
            (string) $this->payment->processor_reference,
            $this->payment->amount,
            $this->payment->currency,
            'rev_'.$this->payment->public_id,
        );

        if (! $result->successful) {
            throw new RuntimeException('The bank refused to reverse the charge: '.$result->failureReason);
        }

        Log::warning('Reversed a charge captured after its payment had failed.', ['payment' => $this->payment->public_id, 'reversal' => $result->processorReference]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::critical('Could not reverse a charge captured after its payment had failed. Refund it by hand.', [
            'payment' => $this->payment->public_id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
