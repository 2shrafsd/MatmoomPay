<?php

use App\Actions\Refunds\CreateRefund;
use App\Enums\PaymentStatus;
use App\Exceptions\GatewayException;
use App\Livewire\PortalComponent;
use App\Models\Payment;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;

new #[Title('Transaction')] class extends PortalComponent {
    public Payment $payment;

    public string $refundAmount = '';

    public string $refundReason = '';

    /**
     * Livewire snapshots outlive the page, so the record is re-authorized on every update, not only at mount.
     */
    public function hydrate(): void
    {
        $this->authorize('view', $this->payment);
        $this->ensureInAccount($this->payment);
    }

    public function mount(Payment $payment): void
    {
        $this->authorize('view', $payment);
        $this->ensureInAccount($payment);

        $this->payment = $payment;
        $this->refundAmount = Money::decimal($payment->refundableAmount());
    }

    public function refund(CreateRefund $createRefund): void
    {
        $this->authorize('refund', $this->payment);
        $this->ensureInAccount($this->payment);

        $this->validate([
            'refundAmount' => ['required', 'string', Money::amountRule()],
            'refundReason' => ['required', 'string', 'max:255'],
        ], [
            'refundAmount.regex' => __('Enter the amount in :currency, for example :example', ['currency' => \App\Support\Money::currency(), 'example' => \App\Support\Money::example()]),
        ]);

        $amount = Money::toMinorUnits($this->refundAmount);

        if ($amount < 1) {
            $this->addError('refundAmount', __('Enter an amount greater than zero.'));

            return;
        }

        try {
            $createRefund->handle($this->payment, $amount, $this->refundReason, [], Auth::user());
        } catch (GatewayException $exception) {
            $this->addError('refundAmount', __($exception->data['error'][0]['message'] ?? $exception->getMessage()));

            return;
        }

        $this->payment->refresh();
        $this->refundReason = '';
        $this->refundAmount = Money::decimal($this->payment->refundableAmount());

        Flux::modal('refund')->close();
        Flux::toast(variant: 'success', text: __('Refund sent. It can take 5 to 10 working days to reach the card.'));
    }

    public function with(): array
    {
        $this->payment->load(['checkoutSession', 'paymentIntent', 'customer', 'refunds.creator:id,name']);

        return [
            'canRefund' => Auth::user()->can('refund', $this->payment) && $this->payment->refundableAmount() > 0,
        ];
    }
}; ?>

@php
    $session = $payment->checkoutSession;
    $metadata = $session?->metadata ?? $payment->paymentIntent?->metadata ?? [];
@endphp

<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('portal.payments')" wire:navigate>{{ __('Transactions') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item><span class="ltr-nums">{{ $payment->public_id }}</span></flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <section class="surface p-5 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="ltr-nums text-3xl font-bold tracking-tight"><span class="sr-only">{{ __('Transaction') }} </span>{{ Money::format($payment->amount, $payment->currency) }}</h1>
                    <x-portal.status :status="$payment->status" />
                </div>
                <flux:text class="mt-1">
                    {{ $payment->card_brand->value }} {{ $payment->card_type->value }} <span class="ltr-nums">{{ $payment->masked_card }}</span>
                </flux:text>
                @if ($payment->failure_reason)
                    <flux:text class="mt-2 text-red-600">{{ __($payment->failure_reason) }}</flux:text>
                @endif
            </div>

            @if ($canRefund)
                <flux:modal.trigger name="refund">
                    <flux:button icon="arrow-uturn-left" data-test="open-refund">{{ __('Refund') }}</flux:button>
                </flux:modal.trigger>
            @endif
        </div>

        <dl class="mt-6 grid gap-x-6 gap-y-4 border-t border-zinc-200 pt-6 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div><dt class="text-zinc-500">{{ __('Payment ID') }}</dt><dd class="ltr-nums mt-0.5 text-start font-mono">{{ $payment->public_id }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('Fee') }}</dt><dd class="ltr-nums mt-0.5 text-start">{{ Money::format($payment->fee, $payment->currency) }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('Net amount') }}</dt><dd class="ltr-nums mt-0.5 text-start">{{ Money::format($payment->amount - $payment->fee - $payment->refunded_amount, $payment->currency) }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('Created') }}</dt><dd class="mt-0.5">{{ $payment->created_at->locale(app()->getLocale())->translatedFormat('j M Y · H:i:s') }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('Completed') }}</dt><dd class="mt-0.5">{{ $payment->completed_at?->locale(app()->getLocale())->translatedFormat('j M Y · H:i:s') ?? '—' }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('Verification') }}</dt><dd class="mt-0.5">{{ match ($payment->challenge_type?->value) { 'otp' => __('One-time code'), '3ds' => '3-D Secure', default => __('Not required') } }}</dd></div>
            @if ($session)
                <div><dt class="text-zinc-500">{{ __('Invoice') }}</dt><dd class="ltr-nums mt-0.5 text-start">#{{ $session->invoice }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Your reference') }}</dt><dd class="ltr-nums mt-0.5 text-start break-all">{{ $session->client_reference_id }}</dd></div>
            @endif
            @if ($payment->paymentIntent)
                <div><dt class="text-zinc-500">{{ __('Payment intent') }}</dt><dd class="ltr-nums mt-0.5 text-start font-mono text-xs break-all">{{ $payment->paymentIntent->public_id }}</dd></div>
            @endif
            @if ($payment->customer)
                <div><dt class="text-zinc-500">{{ __('Customer') }}</dt><dd class="mt-0.5"><flux:link :href="route('portal.customers.show', $payment->customer)" wire:navigate class="ltr-nums break-all">{{ $payment->customer->client_customer_id }}</flux:link></dd></div>
            @endif
        </dl>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        @if ($session)
            <section class="surface p-5 sm:p-6" aria-labelledby="items-heading">
                <flux:heading id="items-heading">{{ __('Items') }}</flux:heading>
                <ul class="mt-3 divide-y divide-zinc-200 text-sm">
                    @foreach ($session->products as $product)
                        <li class="flex justify-between gap-4 py-2.5">
                            <span class="break-words">{{ $product['name'] }} <span class="ltr-nums text-zinc-500">× {{ $product['quantity'] }}</span></span>
                            <span class="ltr-nums shrink-0">{{ Money::decimal($product['quantity'] * $product['unit_amount']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="surface p-5 sm:p-6" aria-labelledby="metadata-heading">
            <flux:heading id="metadata-heading">{{ __('Metadata') }}</flux:heading>
            @if (empty($metadata))
                <flux:text class="mt-3">{{ __('No metadata was sent with this payment.') }}</flux:text>
            @else
                <dl class="mt-3 divide-y divide-zinc-200 text-sm">
                    @foreach ($metadata as $key => $value)
                        <div class="flex justify-between gap-4 py-2.5">
                            <dt class="text-zinc-500 break-words">{{ $key }}</dt>
                            <dd class="text-end break-all">{{ is_bool($value) ? ($value ? 'true' : 'false') : $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </section>
    </div>

    <section class="surface p-5 sm:p-6" aria-labelledby="refunds-heading">
        <flux:heading id="refunds-heading">{{ __('Refunds') }}</flux:heading>
        @if ($payment->refunds->isEmpty())
            <flux:text class="mt-3">{{ __('This payment has not been refunded.') }}</flux:text>
        @else
            <ul class="mt-3 divide-y divide-zinc-200 text-sm">
                @foreach ($payment->refunds as $refund)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3" wire:key="refund-{{ $refund->public_id }}">
                        <div>
                            <p class="ltr-nums text-start font-medium">{{ Money::format($refund->amount, $payment->currency) }}</p>
                            <p class="text-zinc-500">{{ $refund->reason }}@if ($refund->creator) · {{ $refund->creator->name }}@endif</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-portal.status :status="$refund->status" />
                            <span class="text-zinc-500">{{ $refund->created_at->locale(app()->getLocale())->translatedFormat('j M Y · H:i') }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($canRefund)
        <flux:modal name="refund" class="w-full max-w-md">
            <form wire:submit="refund" class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('Refund this payment') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Up to :amount can be refunded. The fee is not returned.', ['amount' => Money::format($payment->refundableAmount(), $payment->currency)]) }}</flux:text>
                </div>
                <flux:input wire:model="refundAmount" :label="__('Amount (:currency)', ['currency' => Money::currency()])" inputmode="decimal" dir="ltr" required />
                <flux:input wire:model="refundReason" :label="__('Reason')" :placeholder="__('For example: item out of stock')" required />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" data-test="confirm-refund">{{ __('Send refund') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
