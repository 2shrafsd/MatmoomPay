<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Refunds\CreateRefund;
use App\Exceptions\GatewayException;
use App\Models\Payment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gives back a payment the bank captured after its checkout, intent or payment link could no longer take it.
 * Retries for about a day; a payer must never be left charged for something they did not get.
 */
class RefundUnappliedPayment implements ShouldQueue
{
    use Queueable;

    public const REASON = 'Automatic refund: the payment arrived after the order was already paid, cancelled or closed.';

    public int $tries = 8;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900, 1800, 3600, 7200, 21600];

    public function __construct(public Payment $payment) {}

    public function handle(CreateRefund $createRefund): void
    {
        try {
            $createRefund->handle($this->payment->refresh(), null, self::REASON);
        } catch (GatewayException $exception) {
            // 4301: already fully refunded or a refund is pending. Nothing left to give back.
            if ($exception->apiCode !== 4301) {
                throw $exception;
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::critical('Could not refund a payment that was captured but not applied. Refund it by hand.', [
            'payment' => $this->payment->public_id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
