<?php

use App\Actions\Audit\RecordAuditLog;
use App\Livewire\PortalComponent;
use App\Models\Customer;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;

new #[Title('Customer')] class extends PortalComponent {
    public Customer $customer;

    /**
     * Livewire snapshots outlive the page, so the record is re-authorized on every update, not only at mount.
     */
    public function hydrate(): void
    {
        $this->authorize('view', $this->customer);
        $this->ensureInAccount($this->customer);
    }

    public function mount(Customer $customer): void
    {
        $this->authorize('view', $customer);
        $this->ensureInAccount($customer);
        $this->customer = $customer;
    }

    public function removeCard(string $cardId, RecordAuditLog $recordAuditLog): void
    {
        $this->authorize('delete', $this->customer);
        $this->ensureInAccount($this->customer);

        $card = $this->customer->paymentMethods()->where('public_id', $cardId)->firstOrFail();
        $card->delete();

        $recordAuditLog->handle(Auth::user(), 'payment_method.deleted', $this->merchant(), $this->customer, ['last4' => $card->last_four]);

        Flux::toast(variant: 'success', text: __('The card was removed.'));
    }

    public function with(): array
    {
        return [
            'cards' => $this->customer->paymentMethods()->latest('id')->get(),
            'payments' => $this->customer->payments()->latest('id')->limit(20)->get(),
            'canManage' => Auth::user()->can('delete', $this->customer),
        ];
    }
}; ?>

<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('portal.customers')" wire:navigate>{{ __('Customers') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item><span class="ltr-nums">{{ $customer->client_customer_id }}</span></flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <section class="surface p-5 sm:p-6">
        <flux:heading size="lg" level="1" class="ltr-nums text-start break-all">{{ $customer->client_customer_id }}</flux:heading>
        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-zinc-500">{{ __('Customer ID') }}</dt><dd class="mt-1"><x-portal.copy :value="$customer->public_id" /></dd></div>
            <div><dt class="text-zinc-500">{{ __('Created') }}</dt><dd class="mt-1">{{ $customer->created_at->locale(app()->getLocale())->translatedFormat('j M Y · H:i') }}</dd></div>
        </dl>
    </section>

    <section class="surface p-5 sm:p-6" aria-labelledby="cards-heading">
        <flux:heading id="cards-heading">{{ __('Saved cards') }}</flux:heading>
        @if ($cards->isEmpty())
            <flux:text class="mt-3">{{ __('This customer has not saved a card yet.') }}</flux:text>
        @else
            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ($cards as $card)
                    <li class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200 p-4" wire:key="card-{{ $card->public_id }}">
                        <div>
                            <p class="font-medium">{{ $card->brand->value }} <span class="ltr-nums">•••• {{ $card->last_four }}</span></p>
                            <p class="text-sm text-zinc-500">{{ $card->card_type->value }} · {{ __('Expires') }} <span class="ltr-nums">{{ sprintf('%02d/%d', $card->expiry_month, $card->expiry_year) }}</span></p>
                        </div>
                        @if ($canManage)
                            <flux:button size="sm" variant="ghost" icon="trash" x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Remove this card? The customer will need to enter it again next time.')) }}, danger: true, run: () => $wire.removeCard('{{ $card->public_id }}') })" :aria-label="__('Remove card')" />
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="surface p-5 sm:p-6" aria-labelledby="payments-heading">
        <flux:heading id="payments-heading">{{ __('Recent payments') }}</flux:heading>
        @if ($payments->isEmpty())
            <flux:text class="mt-3">{{ __('No payments from this customer yet.') }}</flux:text>
        @else
            <ul class="mt-3 divide-y divide-zinc-200 text-sm">
                @foreach ($payments as $payment)
                    <li class="flex items-center justify-between gap-3 py-3" wire:key="pay-{{ $payment->public_id }}">
                        <a href="{{ route('portal.payments.show', $payment) }}" wire:navigate class="ltr-nums font-medium hover:underline">{{ Money::format($payment->amount, $payment->currency) }}</a>
                        <div class="flex items-center gap-3">
                            <x-portal.status :status="$payment->status" />
                            <span class="text-zinc-500">{{ $payment->created_at->locale(app()->getLocale())->translatedFormat('j M Y') }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
