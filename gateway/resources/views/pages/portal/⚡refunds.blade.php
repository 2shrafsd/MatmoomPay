<?php

use App\Livewire\PortalComponent;
use App\Models\Refund;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\WithPagination;

new #[Title('Refunds')] class extends PortalComponent {
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, Refund>
     */
    #[Computed]
    public function refunds(): LengthAwarePaginator
    {
        return Refund::query()
            ->forAccount($this->merchant(), $this->livemode())
            ->with(['payment:id,public_id,masked_card,currency', 'creator:id,name'])
            ->latest('id')
            ->paginate(20);
    }
}; ?>

<div>
    <x-portal.page-header :heading="__('Refunds')" :subheading="__('Money returned to your customers. Refunds are sent from a transaction page or through the API.')" />

    <section class="surface transition-opacity" wire:loading.class.delay="opacity-60" wire:loading.attr.delay="aria-busy">
        @if ($this->refunds->isEmpty())
            <x-portal.empty icon="arrow-uturn-left" :heading="__('No refunds')" :body="__('When you refund a payment, it shows up here.')" />
        @else
            <div class="data-table">
                <flux:table :paginate="$this->refunds">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Amount') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column class="max-sm:hidden">{{ __('Payment') }}</flux:table.column>
                        <flux:table.column class="max-lg:hidden">{{ __('Reason') }}</flux:table.column>
                        <flux:table.column class="max-md:hidden">{{ __('By') }}</flux:table.column>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->refunds as $refund)
                            <flux:table.row :key="$refund->public_id">
                                <flux:table.cell variant="strong"><span class="ltr-nums">{{ Money::format($refund->amount, $refund->payment->currency) }}</span></flux:table.cell>
                                <flux:table.cell><x-portal.status :status="$refund->status" /></flux:table.cell>
                                <flux:table.cell class="max-sm:hidden"><flux:link :href="route('portal.payments.show', $refund->payment)" wire:navigate class="ltr-nums font-mono text-xs">{{ $refund->payment->public_id }}</flux:link></flux:table.cell>
                                <flux:table.cell class="max-lg:hidden">{{ \Illuminate\Support\Str::limit($refund->reason, 40) }}</flux:table.cell>
                                <flux:table.cell class="max-md:hidden">{{ $refund->creator?->name ?? __('API') }}</flux:table.cell>
                                <flux:table.cell>{{ $refund->created_at->locale(app()->getLocale())->translatedFormat('j M Y · H:i') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </section>
</div>
