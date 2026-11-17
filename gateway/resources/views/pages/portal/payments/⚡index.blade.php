<?php

use App\Enums\PaymentStatus;
use App\Livewire\PortalComponent;
use App\Models\Payment;
use App\Queries\PaymentSearch;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

new #[Title('Transactions')] class extends PortalComponent {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'from', 'to');
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Payment>
     */
    #[Computed]
    public function payments(): LengthAwarePaginator
    {
        return $this->search()->query()->paginate(20);
    }

    #[Computed]
    public function exportUrl(): string
    {
        return route('portal.payments.export', array_filter(['q' => $this->search, 'status' => $this->status, 'from' => $this->from, 'to' => $this->to]));
    }

    private function search(): PaymentSearch
    {
        return new PaymentSearch($this->account(), $this->search, $this->status, $this->from, $this->to);
    }
}; ?>

<div>
    <x-portal.page-header :heading="__('Transactions')" :subheading="__('Every card payment attempt made through your checkout, payment links and API.')">
        <x-slot:actions>
            <flux:button :href="$this->exportUrl" icon="arrow-down-tray" data-test="export-payments">{{ __('Export CSV') }}</flux:button>
        </x-slot:actions>
    </x-portal.page-header>

    <div class="surface mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))_auto] lg:items-end">
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :label="__('Search')" :placeholder="__('Payment ID, invoice, reference or last 4 digits')" />
        <flux:select wire:model.live="status" :label="__('Status')">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (PaymentStatus::cases() as $case)
                <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live="from" type="date" :label="__('From')" />
        <flux:input wire:model.live="to" type="date" :label="__('To')" />
        <flux:button wire:click="clearFilters" variant="ghost" icon="x-mark">{{ __('Clear') }}</flux:button>
    </div>

    <section class="surface transition-opacity" wire:loading.class.delay="opacity-60" wire:loading.attr.delay="aria-busy">
        @if ($this->payments->isEmpty())
            @if ($search !== '' || $status !== '' || $from !== '' || $to !== '')
                <x-portal.empty icon="banknotes" :heading="__('No transactions match')" :body="__('Try a different search or clear the filters.')" />
            @else
                <x-portal.empty icon="banknotes" :heading="__('No transactions yet')" :body="__('Payments appear here as soon as a customer pays. In test mode, open a payment link and pay with a test card to see one.')" />
            @endif
        @else
            <div class="data-table">
                <flux:table :paginate="$this->payments">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Amount') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column class="max-md:hidden">{{ __('Payment ID') }}</flux:table.column>
                        <flux:table.column class="max-sm:hidden">{{ __('Card') }}</flux:table.column>
                        <flux:table.column class="max-lg:hidden">{{ __('Reference') }}</flux:table.column>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->payments as $payment)
                            <flux:table.row :key="$payment->public_id">
                                <flux:table.cell variant="strong">
                                    <a href="{{ route('portal.payments.show', $payment) }}" wire:navigate class="ltr-nums hover:underline">{{ Money::format($payment->amount, $payment->currency) }}</a>
                                    @if ($payment->refunded_amount > 0)
                                        <flux:badge size="sm" color="zinc" class="ms-1">{{ $payment->refunded_amount >= $payment->amount ? __('Refunded') : __('Partly refunded') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell><x-portal.status :status="$payment->status" /></flux:table.cell>
                                <flux:table.cell class="max-md:hidden"><span class="ltr-nums font-mono text-xs">{{ $payment->public_id }}</span></flux:table.cell>
                                <flux:table.cell class="max-sm:hidden"><span class="ltr-nums">{{ $payment->card_brand->value }} •••• {{ $payment->card_last_four }}</span></flux:table.cell>
                                <flux:table.cell class="max-lg:hidden"><span class="ltr-nums">{{ $payment->checkoutSession?->client_reference_id ?? $payment->paymentIntent?->client_reference_id ?? '—' }}</span></flux:table.cell>
                                <flux:table.cell>{{ $payment->created_at->locale(app()->getLocale())->translatedFormat('j M Y · H:i') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </section>
</div>
