<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Payment;
use App\Services\Payments\Contracts\HostedPaymentPageProcessor;
use App\Services\Payments\Contracts\PaymentProcessor;
use App\Services\Payments\Exceptions\ProcessorUnavailable;
use App\Services\Payments\Processors\SandboxHostedProcessor;
use App\Services\Payments\Processors\SandboxProcessor;
use App\Services\Payments\Processors\UnconfiguredProcessor;
use Illuminate\Contracts\Container\Container;

/**
 * Picks the processor for a charge. Test mode is pinned to the sandbox so test keys can never
 * move real money; live mode uses the driver configured in gateway.processors.live.
 */
final readonly class PaymentProcessorResolver
{
    public function __construct(private Container $container) {}

    public function for(bool $livemode): PaymentProcessor
    {
        if (! $livemode) {
            return config('gateway.sandbox.flow') === 'hosted'
                ? $this->container->make(SandboxHostedProcessor::class)
                : $this->container->make(SandboxProcessor::class);
        }

        return match (config('gateway.processors.live')) {
            // Register real acquirer drivers here, e.g. 'bank_muscat' => $this->container->make(BankMuscatProcessor::class),
            default => $this->container->make(UnconfiguredProcessor::class),
        };
    }

    /**
     * The processor that handled this payment. Its token and reference mean nothing to any other bank,
     * so if the platform has since switched acquirer the call is refused instead of sent elsewhere.
     *
     * @throws ProcessorUnavailable
     */
    public function forPayment(Payment $payment): PaymentProcessor
    {
        $processor = $this->for($payment->livemode);

        if ($processor->name() !== $payment->processor) {
            throw ProcessorUnavailable::notConfigured();
        }

        return $processor;
    }

    /**
     * The processor for this mode when it collects cards on its own page, otherwise null.
     */
    public function hostedFor(bool $livemode): ?HostedPaymentPageProcessor
    {
        $processor = $this->for($livemode);

        return $processor instanceof HostedPaymentPageProcessor ? $processor : null;
    }
}
