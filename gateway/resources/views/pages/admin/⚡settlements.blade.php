<?php

use App\Actions\Settlements\CreateSettlement;
use App\Enums\SettlementStatus;
use App\Exceptions\GatewayException;
use App\Models\Settlement;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin'), Title('Settlements')] class extends Component {
    use WithPagination;

    #[Locked]
    public ?string $settlementId = null;

    public string $bankReference = '';

    public string $failureReason = '';

    public function startMarkingPaid(string $settlementId): void
    {
        $this->settlementId = Settlement::query()->where('public_id', $settlementId)->where('status', SettlementStatus::Pending)->firstOrFail()->public_id;
        $this->bankReference = '';
        Flux::modal('mark-paid')->show();
    }

    public function markPaid(CreateSettlement $createSettlement): void
    {
        $this->validate(['bankReference' => ['required', 'string', 'max:80']]);

        $settlement = Settlement::query()->where('public_id', $this->settlementId)->firstOrFail();

        try {
            $createSettlement->markPaid($settlement, $this->bankReference, Auth::user());
        } catch (GatewayException $exception) {
            $this->addError('bankReference', $exception->getMessage());

            return;
        }

        Flux::modal('mark-paid')->close();
        Flux::toast(variant: 'success', text: __('Settlement marked as transferred.'));
    }

    public function startMarkingFailed(string $settlementId): void
    {
        $this->settlementId = Settlement::query()->where('public_id', $settlementId)->where('status', SettlementStatus::Pending)->firstOrFail()->public_id;
        $this->failureReason = '';
        Flux::modal('mark-failed')->show();
    }

    public function markFailed(CreateSettlement $createSettlement): void
    {
        $this->validate(['failureReason' => ['required', 'string', 'max:255']]);

        $settlement = Settlement::query()->where('public_id', $this->settlementId)->firstOrFail();

        try {
            $createSettlement->markFailed($settlement, $this->failureReason, Auth::user());
        } catch (GatewayException $exception) {
            $this->addError('failureReason', $exception->getMessage());

            return;
        }

        Flux::modal('mark-failed')->close();
        Flux::toast(variant: 'success', text: __('Settlement marked as failed. Its balance goes into the next settlement.'));
    }

    /**
     * @return LengthAwarePaginator<int, Settlement>
     */
    #[Computed]
    public function settlements(): LengthAwarePaginator
    {
        return Settlement::query()->with('merchant:id,name,public_id,settlement_bank_name')->latest('id')->paginate(25);
    }
}; ?>

<div>
    <x-portal.page-header :heading="__('Settlements')" :subheading="__('Create settlements from a merchant page, then confirm each bank transfer here.')" />

    <section class="surface transition-opacity" wire:loading.class.delay="opacity-60" wire:loading.attr.delay="aria-busy">
        @if ($this->settlements->isEmpty())
            <x-portal.empty icon="building-library" :heading="__('No settlements yet')" />
        @else
            <div class="data-table">
                <flux:table :paginate="$this->settlements">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Net amount') }}</flux:table.column>
                        <flux:table.column>{{ __('Merchant') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column class="max-lg:hidden">{{ __('Paid to') }}</flux:table.column>
                        <flux:table.column class="max-md:hidden">{{ __('Bank reference') }}</flux:table.column>
                        <flux:table.column class="max-sm:hidden">{{ __('Created') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->settlements as $settlement)
                            <flux:table.row :key="$settlement->public_id">
                                <flux:table.cell variant="strong"><span class="ltr-nums">{{ Money::format($settlement->net_amount, $settlement->currency) }}</span></flux:table.cell>
                                <flux:table.cell><flux:link :href="route('admin.merchants.show', $settlement->merchant)" wire:navigate>{{ $settlement->merchant->name }}</flux:link></flux:table.cell>
                                <flux:table.cell><x-portal.status :status="$settlement->status" /></flux:table.cell>
                                <flux:table.cell class="max-lg:hidden"><span class="ltr-nums">{{ $settlement->bank_name ? $settlement->bank_name.' · '.$settlement->maskedIban() : '—' }}</span></flux:table.cell>
                                <flux:table.cell class="max-md:hidden"><span class="ltr-nums">{{ $settlement->bank_reference ?? '—' }}</span>@if ($settlement->failure_reason)<span class="block text-xs text-red-600">{{ $settlement->failure_reason }}</span>@endif</flux:table.cell>
                                <flux:table.cell class="max-sm:hidden">{{ $settlement->created_at->locale(app()->getLocale())->translatedFormat('j M Y') }}</flux:table.cell>
                                <flux:table.cell class="text-end">
                                    @if ($settlement->status === SettlementStatus::Pending)
                                        <div class="flex justify-end gap-2">
                                            <flux:button size="sm" wire:click="startMarkingPaid('{{ $settlement->public_id }}')">{{ __('Mark transferred') }}</flux:button>
                                            <flux:button size="sm" variant="ghost" wire:click="startMarkingFailed('{{ $settlement->public_id }}')">{{ __('Transfer failed') }}</flux:button>
                                        </div>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </section>

    <flux:modal name="mark-paid" class="w-full max-w-md">
        <form wire:submit="markPaid" class="space-y-5">
            <flux:heading size="lg">{{ __('Confirm the bank transfer') }}</flux:heading>
            <flux:input wire:model="bankReference" :label="__('Bank transfer reference')" dir="ltr" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Mark transferred') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="mark-failed" class="w-full max-w-md">
        <form wire:submit="markFailed" class="space-y-5">
            <flux:heading size="lg">{{ __('The bank returned this transfer') }}</flux:heading>
            <flux:text>{{ __('The balance is released and will be included in the next settlement.') }}</flux:text>
            <flux:input wire:model="failureReason" :label="__('What did the bank say?')" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Mark failed') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
