<?php

use App\Livewire\PortalComponent;
use App\Models\BalanceTransaction;
use App\Models\Settlement;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\WithPagination;

new #[Title('Settlement')] class extends PortalComponent {
    use WithPagination;

    #[Locked]
    public string $settlementId = '';

    public function mount(string $settlement): void
    {
        $this->settlementId = $this->owned($settlement)->public_id;
    }

    #[Computed]
    public function settlement(): Settlement
    {
        return $this->owned($this->settlementId);
    }

    /**
     * @return LengthAwarePaginator<int, BalanceTransaction>
     */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return BalanceTransaction::query()->where('settlement_id', $this->settlement->id)->oldest('id')->paginate(50);
    }

    /**
     * Settlements belong to the merchant, never to another account.
     */
    private function owned(string $publicId): Settlement
    {
        return Settlement::query()->where('merchant_id', $this->merchant()->id)->where('public_id', $publicId)->firstOrFail();
    }
}; ?>

<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('portal.settlements')" wire:navigate>{{ __('Settlements') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item><span class="ltr-nums">{{ $this->settlement->public_id }}</span></flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <section class="surface p-5 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="ltr-nums text-3xl font-bold tracking-tight"><span class="sr-only">{{ __('Settlement') }} </span>{{ Money::format($this->settlement->net_amount, $this->settlement->currency) }}</h1>
                    <x-portal.status :status="$this->settlement->status" />
                </div>
                <flux:text class="mt-1">{{ __('Paid to :account', ['account' => $this->settlement->bank_name ? $this->settlement->bank_name.' · '.$this->settlement->maskedIban() : '—']) }}</flux:text>
                @if ($this->settlement->failure_reason)
                    <flux:text class="mt-2 text-red-600">{{ $this->settlement->failure_reason }}</flux:text>
                @endif
            </div>
            <flux:button icon="arrow-down-tray" :href="route('portal.settlements.export', $this->settlement->public_id)">{{ __('Download CSV') }}</flux:button>
        </div>

        <dl class="mt-6 grid gap-x-6 gap-y-4 border-t border-zinc-200 pt-6 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-zinc-500">{{ __('Gross payments') }}</dt><dd class="ltr-nums mt-0.5 text-start">{{ Money::format($this->settlement->gross_amount) }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('Fees') }}</dt><dd class="ltr-nums mt-0.5 text-start">−{{ Money::format($this->settlement->fee_amount) }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('Refunds') }}</dt><dd class="ltr-nums mt-0.5 text-start">−{{ Money::format($this->settlement->refund_amount) }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('Bank reference') }}</dt><dd class="ltr-nums mt-0.5 text-start">{{ $this->settlement->bank_reference ?? '—' }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('Period') }}</dt><dd class="mt-0.5">{{ $this->settlement->period_start?->locale(app()->getLocale())->translatedFormat('j M Y') }} – {{ $this->settlement->period_end?->locale(app()->getLocale())->translatedFormat('j M Y') }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('Transferred') }}</dt><dd class="mt-0.5">{{ $this->settlement->paid_at?->locale(app()->getLocale())->translatedFormat('j M Y · H:i') ?? '—' }}</dd></div>
        </dl>
    </section>

    <section class="surface transition-opacity" aria-labelledby="entries-heading" wire:loading.class.delay="opacity-60">
        <flux:heading id="entries-heading" class="px-5 pt-5 pb-3">{{ __('Included entries') }}</flux:heading>
        <div class="data-table">
            <flux:table :paginate="$this->entries">
                <flux:table.columns>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column>{{ __('Amount') }}</flux:table.column>
                    <flux:table.column class="max-md:hidden">{{ __('Description') }}</flux:table.column>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->entries as $entry)
                        <flux:table.row :key="'entry-'.$entry->id">
                            <flux:table.cell>{{ $entry->type->label() }}</flux:table.cell>
                            <flux:table.cell variant="strong"><span class="ltr-nums">{{ Money::format($entry->amount, $entry->currency) }}</span></flux:table.cell>
                            <flux:table.cell class="max-md:hidden"><span class="ltr-nums">{{ $entry->description }}</span></flux:table.cell>
                            <flux:table.cell>{{ $entry->created_at?->locale(app()->getLocale())->translatedFormat('j M Y · H:i') }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </section>
</div>
