<?php

use App\Actions\Checkout\CancelCheckoutSession;
use App\Enums\CheckoutPaymentStatus;
use App\Exceptions\GatewayException;
use App\Livewire\PortalComponent;
use App\Models\CheckoutSession;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

new #[Title('Checkout sessions')] class extends PortalComponent {
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function cancel(string $sessionId, CancelCheckoutSession $cancelCheckoutSession): void
    {
        $session = CheckoutSession::query()->forAccount($this->merchant(), $this->livemode())->where('public_id', $sessionId)->firstOrFail();
        $this->authorize('cancel', $session);

        try {
            $cancelCheckoutSession->handle($session);
            Flux::toast(variant: 'success', text: __('The checkout session was cancelled.'));
        } catch (GatewayException $exception) {
            Flux::toast(variant: 'danger', text: __($exception->getMessage()));
        }
    }

    /**
     * @return LengthAwarePaginator<int, CheckoutSession>
     */
    #[Computed]
    public function sessions(): LengthAwarePaginator
    {
        $status = CheckoutPaymentStatus::tryFrom($this->status);
        $search = trim(mb_substr($this->search, 0, 64));

        return CheckoutSession::query()
            ->forAccount($this->merchant(), $this->livemode())
            ->when($status, fn (Builder $query) => $query->where('payment_status', $status))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('invoice', $search)->orWhere('client_reference_id', $search)->orWhere('public_id', $search)))
            ->latest('id')
            ->paginate(20);
    }

    #[Computed]
    public function canCancel(): bool
    {
        return $this->role()->canManageCustomers();
    }
}; ?>

<div>
    <x-portal.page-header :heading="__('Checkout sessions')" :subheading="__('Payment requests created by your website or app. A session becomes paid once the customer completes the card payment.')" />

    <div class="surface mb-4 grid gap-3 p-4 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :label="__('Search')" :placeholder="__('Invoice, reference or session ID')" />
        <flux:select wire:model.live="status" :label="__('Status')">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (CheckoutPaymentStatus::cases() as $case)
                <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <section class="surface transition-opacity" wire:loading.class.delay="opacity-60" wire:loading.attr.delay="aria-busy">
        @if ($this->sessions->isEmpty())
            <x-portal.empty icon="shopping-cart" :heading="__('No checkout sessions yet')" :body="__('Create one with POST /api/v1/checkout/session, or share a payment link.')" />
        @else
            <div class="data-table">
                <flux:table :paginate="$this->sessions">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Total') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column class="max-sm:hidden">{{ __('Invoice') }}</flux:table.column>
                        <flux:table.column class="max-lg:hidden">{{ __('Reference') }}</flux:table.column>
                        <flux:table.column class="max-md:hidden">{{ __('Expires') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->sessions as $session)
                            <flux:table.row :key="$session->public_id">
                                <flux:table.cell variant="strong"><span class="ltr-nums">{{ Money::format($session->total_amount, $session->currency) }}</span></flux:table.cell>
                                <flux:table.cell>
                                    @if ($session->payment_status === CheckoutPaymentStatus::Unpaid && $session->isExpired())
                                        <flux:badge size="sm" color="zinc" inset="top bottom">{{ __('Expired') }}</flux:badge>
                                    @else
                                        <x-portal.status :status="$session->payment_status" />
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="max-sm:hidden"><span class="ltr-nums">#{{ $session->invoice }}</span></flux:table.cell>
                                <flux:table.cell class="max-lg:hidden"><span class="ltr-nums break-all">{{ $session->client_reference_id }}</span></flux:table.cell>
                                <flux:table.cell class="max-md:hidden">{{ $session->expire_at->locale(app()->getLocale())->translatedFormat('j M · H:i') }}</flux:table.cell>
                                <flux:table.cell class="text-end">
                                    @if ($this->canCancel && $session->isPayable())
                                        <flux:button size="sm" variant="ghost" x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Cancel invoice #:invoice? The customer will no longer be able to pay it.', ['invoice' => $session->invoice])) }}, danger: true, run: () => $wire.cancel('{{ $session->public_id }}') })">{{ __('Cancel') }}</flux:button>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </section>
</div>
