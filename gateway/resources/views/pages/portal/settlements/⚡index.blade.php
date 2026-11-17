<?php

use App\Livewire\PortalComponent;
use App\Models\Settlement;
use App\Queries\AccountMetrics;
use App\Support\GatewayAccount;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\WithPagination;

new #[Title('Settlements')] class extends PortalComponent {
    use WithPagination;

    #[Computed]
    public function liveBalance(): int
    {
        return (new AccountMetrics(new GatewayAccount($this->merchant(), true)))->availableBalance();
    }

    /**
     * @return LengthAwarePaginator<int, Settlement>
     */
    #[Computed]
    public function settlements(): LengthAwarePaginator
    {
        return Settlement::query()->where('merchant_id', $this->merchant()->id)->latest('id')->paginate(15);
    }

    public function with(): array
    {
        return ['merchant' => $this->merchant()];
    }
}; ?>

<div>
    <x-portal.page-header :heading="__('Settlements')" :subheading="__('Live payments, minus fees and refunds, are transferred to your bank account in settlement batches.')" />

    <div class="grid gap-4 sm:grid-cols-2">
        <x-portal.stat :label="__('Next settlement (estimate)')" :value="Money::format($this->liveBalance)" :hint="__('Live payments not yet settled')" icon="banknotes" />
        <div class="surface p-4 sm:p-5">
            <flux:text class="text-sm">{{ __('Settlement account') }}</flux:text>
            @if ($merchant->settlement_bank_name)
                <p class="mt-1 font-semibold">{{ $merchant->settlement_bank_name }}</p>
                <p class="ltr-nums text-start text-sm text-zinc-500">{{ $merchant->settlement_account_name }} · {{ $merchant->maskedIban() }}</p>
            @else
                <p class="mt-1 text-sm text-zinc-600">{{ __('Add your bank account so we can transfer your money.') }}</p>
                <flux:link :href="route('portal.business')" wire:navigate class="text-sm">{{ __('Add bank account') }}</flux:link>
            @endif
        </div>
    </div>

    <section class="surface mt-6">
        @if ($this->settlements->isEmpty())
            <x-portal.empty icon="building-library" :heading="__('No settlements yet')" :body="__('Your first settlement appears here after your first live payments.')" />
        @else
            <div class="data-table">
                <flux:table :paginate="$this->settlements">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Net amount') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column class="max-md:hidden">{{ __('Gross payments') }}</flux:table.column>
                        <flux:table.column class="max-md:hidden">{{ __('Fees') }}</flux:table.column>
                        <flux:table.column class="max-lg:hidden">{{ __('Refunds') }}</flux:table.column>
                        <flux:table.column class="max-sm:hidden">{{ __('Bank reference') }}</flux:table.column>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->settlements as $settlement)
                            <flux:table.row :key="$settlement->public_id">
                                <flux:table.cell variant="strong"><a href="{{ route('portal.settlements.show', $settlement->public_id) }}" wire:navigate class="ltr-nums hover:underline">{{ Money::format($settlement->net_amount, $settlement->currency) }}</a></flux:table.cell>
                                <flux:table.cell><x-portal.status :status="$settlement->status" /></flux:table.cell>
                                <flux:table.cell class="max-md:hidden"><span class="ltr-nums">{{ Money::decimal($settlement->gross_amount) }}</span></flux:table.cell>
                                <flux:table.cell class="max-md:hidden"><span class="ltr-nums">{{ Money::decimal($settlement->fee_amount) }}</span></flux:table.cell>
                                <flux:table.cell class="max-lg:hidden"><span class="ltr-nums">{{ Money::decimal($settlement->refund_amount) }}</span></flux:table.cell>
                                <flux:table.cell class="max-sm:hidden"><span class="ltr-nums">{{ $settlement->bank_reference ?? '—' }}</span></flux:table.cell>
                                <flux:table.cell>{{ ($settlement->paid_at ?? $settlement->created_at)->locale(app()->getLocale())->translatedFormat('j M Y') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </section>
</div>
