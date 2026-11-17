<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin'), Title('Payments')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: 'live')]
    public string $mode = 'live';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Payment>
     */
    #[Computed]
    public function payments(): LengthAwarePaginator
    {
        $status = PaymentStatus::tryFrom($this->status);
        $search = trim(mb_substr($this->search, 0, 64));

        return Payment::query()
            ->with('merchant:id,name,public_id')
            ->where('livemode', $this->mode !== 'test')
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('public_id', $search)->orWhere('card_last_four', $search)))
            ->latest('id')
            ->paginate(25);
    }
}; ?>

<div>
    <x-portal.page-header :heading="__('Payments')" :subheading="__('Read-only view of payments across every merchant.')" />

    <div class="surface mb-4 grid gap-3 p-4 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :label="__('Search')" :placeholder="__('Payment ID or last 4 digits')" />
        <flux:select wire:model.live="status" :label="__('Status')">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (PaymentStatus::cases() as $case)
                <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="mode" :label="__('Mode')">
            <flux:select.option value="live">{{ __('Live') }}</flux:select.option>
            <flux:select.option value="test">{{ __('Test') }}</flux:select.option>
        </flux:select>
    </div>

    <section class="surface transition-opacity" wire:loading.class.delay="opacity-60" wire:loading.attr.delay="aria-busy">
        @if ($this->payments->isEmpty())
            <x-portal.empty icon="banknotes" :heading="__('No payments found')" />
        @else
            <div class="data-table">
                <flux:table :paginate="$this->payments">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Amount') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Merchant') }}</flux:table.column>
                        <flux:table.column class="max-md:hidden">{{ __('Payment ID') }}</flux:table.column>
                        <flux:table.column class="max-lg:hidden">{{ __('Fee') }}</flux:table.column>
                        <flux:table.column class="max-sm:hidden">{{ __('Date') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->payments as $payment)
                            <flux:table.row :key="$payment->public_id">
                                <flux:table.cell variant="strong"><span class="ltr-nums">{{ Money::format($payment->amount, $payment->currency) }}</span></flux:table.cell>
                                <flux:table.cell><x-portal.status :status="$payment->status" /></flux:table.cell>
                                <flux:table.cell><flux:link :href="route('admin.merchants.show', $payment->merchant)" wire:navigate>{{ $payment->merchant->name }}</flux:link></flux:table.cell>
                                <flux:table.cell class="max-md:hidden"><span class="ltr-nums font-mono text-xs">{{ $payment->public_id }}</span></flux:table.cell>
                                <flux:table.cell class="max-lg:hidden"><span class="ltr-nums">{{ Money::decimal($payment->fee) }}</span></flux:table.cell>
                                <flux:table.cell class="max-sm:hidden">{{ $payment->created_at->locale(app()->getLocale())->translatedFormat('j M Y · H:i') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </section>
</div>
