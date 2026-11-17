<?php

use App\Enums\BalanceTransactionType;
use App\Livewire\PortalComponent;
use App\Models\BalanceTransaction;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

new #[Title('Balance')] class extends PortalComponent {
    use WithPagination;

    #[Url(except: '')]
    public string $type = '';

    public function updatedType(): void
    {
        $this->resetPage();
    }

    /**
     * What the merchant is owed right now, split into money that can go out in the next settlement and
     * money still in its hold period.
     *
     * @return array{available: int, on_hold: int}
     */
    #[Computed]
    public function totals(): array
    {
        $unsettled = BalanceTransaction::query()->forAccount($this->merchant(), $this->livemode())->whereNull('settlement_id');
        $cutoff = now()->subDays((int) config('gateway.settlements.hold_days'));

        $available = (int) (clone $unsettled)->where(fn (Builder $query) => $query->where('created_at', '<=', $cutoff)->orWhere('type', BalanceTransactionType::Refund))->sum('amount');

        return ['available' => $available, 'on_hold' => (int) $unsettled->sum('amount') - $available];
    }

    /**
     * @return LengthAwarePaginator<int, BalanceTransaction>
     */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return BalanceTransaction::query()
            ->forAccount($this->merchant(), $this->livemode())
            ->with('settlement:id,public_id,status')
            ->when(BalanceTransactionType::tryFrom($this->type), fn (Builder $query, BalanceTransactionType $type) => $query->where('type', $type))
            ->latest('id')
            ->paginate(30);
    }
}; ?>

<div>
    <x-portal.page-header :heading="__('Balance')" :subheading="__('Every payment, fee and refund that moves your balance, and the settlement that paid it out.')" />

    <div class="grid gap-4 sm:grid-cols-2">
        <x-portal.stat :label="__('Available for the next settlement')" :value="Money::format($this->totals['available'])" icon="banknotes" />
        <x-portal.stat :label="__('On hold')" :value="Money::format($this->totals['on_hold'])" :hint="__('New payments wait :days days before they can be settled.', ['days' => config('gateway.settlements.hold_days')])" icon="clock" />
    </div>

    <div class="surface mt-6 p-4">
        <flux:select wire:model.live="type" :label="__('Type')" class="max-w-xs">
            <flux:select.option value="">{{ __('All types') }}</flux:select.option>
            @foreach (BalanceTransactionType::cases() as $case)
                <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <section class="surface mt-4 transition-opacity" wire:loading.class.delay="opacity-60">
        @if ($this->entries->isEmpty())
            <x-portal.empty icon="scale" :heading="__('No balance entries yet')" :body="__('Each successful payment adds an entry here, with its fee and any refund.')" />
        @else
            <div class="data-table">
                <flux:table :paginate="$this->entries">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Type') }}</flux:table.column>
                        <flux:table.column>{{ __('Amount') }}</flux:table.column>
                        <flux:table.column class="max-md:hidden">{{ __('Description') }}</flux:table.column>
                        <flux:table.column>{{ __('Settlement') }}</flux:table.column>
                        <flux:table.column class="max-sm:hidden">{{ __('Date') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->entries as $entry)
                            <flux:table.row :key="'entry-'.$entry->id">
                                <flux:table.cell>{{ $entry->type->label() }}</flux:table.cell>
                                <flux:table.cell variant="strong"><span @class(['ltr-nums', 'text-red-700' => $entry->amount < 0])>{{ Money::format($entry->amount, $entry->currency) }}</span></flux:table.cell>
                                <flux:table.cell class="max-md:hidden"><span class="ltr-nums">{{ $entry->description }}</span></flux:table.cell>
                                <flux:table.cell>
                                    @if ($entry->settlement)
                                        <a href="{{ route('portal.settlements.show', $entry->settlement->public_id) }}" wire:navigate class="ltr-nums underline underline-offset-2">{{ $entry->settlement->public_id }}</a>
                                    @else
                                        <span class="text-zinc-500">{{ __('Not settled yet') }}</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="max-sm:hidden">{{ $entry->created_at?->locale(app()->getLocale())->translatedFormat('j M Y · H:i') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </section>
</div>
