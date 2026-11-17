<?php

use App\Livewire\PortalComponent;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

new #[Title('Customers')] class extends PortalComponent {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Customer>
     */
    #[Computed]
    public function customers(): LengthAwarePaginator
    {
        $search = trim(mb_substr($this->search, 0, 64));

        return Customer::query()
            ->forAccount($this->merchant(), $this->livemode())
            ->withCount(['paymentMethods', 'payments'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('client_customer_id', 'like', '%'.addcslashes($search, '%_\\').'%')->orWhere('public_id', $search)))
            ->latest('id')
            ->paginate(20);
    }
}; ?>

<div>
    <x-portal.page-header :heading="__('Customers')" :subheading="__('Customers you created through the API so they can save cards and pay faster.')" />

    <div class="surface mb-4 p-4">
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :label="__('Search')" :placeholder="__('Your customer ID or email')" />
    </div>

    <section class="surface transition-opacity" wire:loading.class.delay="opacity-60" wire:loading.attr.delay="aria-busy">
        @if ($this->customers->isEmpty())
            @if ($search !== '')
                <x-portal.empty icon="users" :heading="__('No customers match')" :body="__('Try a different search.')" />
            @else
                <x-portal.empty icon="users" :heading="__('No customers yet')" :body="__('Create customers with POST /api/v1/customers and pass customer_id when you create a checkout session.')" />
            @endif
        @else
            <div class="data-table">
                <flux:table :paginate="$this->customers">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Customer') }}</flux:table.column>
                        <flux:table.column class="max-sm:hidden">{{ __('Saved cards') }}</flux:table.column>
                        <flux:table.column class="max-sm:hidden">{{ __('Payments') }}</flux:table.column>
                        <flux:table.column>{{ __('Created') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->customers as $customer)
                            <flux:table.row :key="$customer->public_id">
                                <flux:table.cell variant="strong">
                                    <a href="{{ route('portal.customers.show', $customer) }}" wire:navigate class="ltr-nums break-all hover:underline">{{ $customer->client_customer_id }}</a>
                                </flux:table.cell>
                                <flux:table.cell class="max-sm:hidden">{{ $customer->payment_methods_count }}</flux:table.cell>
                                <flux:table.cell class="max-sm:hidden">{{ $customer->payments_count }}</flux:table.cell>
                                <flux:table.cell>{{ $customer->created_at->locale(app()->getLocale())->translatedFormat('j M Y') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </section>
</div>
