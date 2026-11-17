<?php

use App\Actions\Merchants\ArchiveMerchant;
use App\Actions\Merchants\ChangeMerchantStatus;
use App\Enums\MerchantStatus;
use App\Exceptions\GatewayException;
use App\Models\Merchant;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin'), Title('Merchants')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Locked]
    public string $actingOn = '';

    #[Locked]
    public string $targetStatus = '';

    public string $reason = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'search'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Opens the reason dialog for a decision the merchant must be told about (suspend, ban, archive).
     */
    public function askReason(string $merchantId, string $action): void
    {
        abort_unless(in_array($action, ['suspended', 'banned', 'archive'], true), 422);

        $this->actingOn = $merchantId;
        $this->targetStatus = $action;
        $this->reason = '';
        $this->resetValidation();

        Flux::modal('merchant-reason')->show();
    }

    public function confirmWithReason(ChangeMerchantStatus $changeMerchantStatus, ArchiveMerchant $archiveMerchant): void
    {
        $this->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => __('Write a reason. The merchant will see it.')]);

        $merchant = $this->find($this->actingOn);

        if ($this->targetStatus === 'archive') {
            $archiveMerchant->archive($merchant, Auth::user(), $this->reason);
            $message = __(':name was archived. Its API keys no longer work.', ['name' => $merchant->name]);
        } else {
            $target = MerchantStatus::from($this->targetStatus);
            $changeMerchantStatus->handle($merchant, $target, Auth::user(), $this->reason);
            $message = __('Merchant status changed to :status.', ['status' => $target->label()]);
        }

        $this->reset('actingOn', 'targetStatus', 'reason');
        Flux::modal('merchant-reason')->close();
        Flux::toast(variant: 'success', text: $message);
    }

    public function reactivate(string $merchantId, ChangeMerchantStatus $changeMerchantStatus): void
    {
        try {
            $changeMerchantStatus->handle($this->find($merchantId), MerchantStatus::Active, Auth::user());
        } catch (GatewayException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Merchant status changed to :status.', ['status' => MerchantStatus::Active->label()]));
    }

    public function restore(string $merchantId, ArchiveMerchant $archiveMerchant): void
    {
        $merchant = $this->find($merchantId);
        $archiveMerchant->restore($merchant, Auth::user());

        Flux::toast(variant: 'success', text: __(':name is back. The team needs to create new API keys.', ['name' => $merchant->name]));
    }

    public function destroy(string $merchantId, ArchiveMerchant $archiveMerchant): void
    {
        $merchant = $this->find($merchantId);

        try {
            $archiveMerchant->destroy($merchant, Auth::user());
        } catch (GatewayException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __(':name was deleted permanently.', ['name' => $merchant->name]));
    }

    private function find(string $merchantId): Merchant
    {
        return Merchant::withTrashed()->where('public_id', $merchantId)->firstOrFail();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        $byStatus = Merchant::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();

        return [
            '' => array_sum($byStatus),
            ...$byStatus,
            'archived' => Merchant::onlyTrashed()->count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Merchant>
     */
    #[Computed]
    public function merchants(): LengthAwarePaginator
    {
        $status = MerchantStatus::tryFrom($this->status);
        $search = trim(mb_substr($this->search, 0, 64));
        $like = '%'.addcslashes($search, '%_\\').'%';

        return Merchant::query()
            ->when($this->status === 'archived', fn (Builder $query) => $query->onlyTrashed())
            ->withCount('users')
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', $like)
                ->orWhere('legal_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('commercial_registration', $search)
                ->orWhere('public_id', $search)))
            ->latest('id')
            ->paginate(20);
    }
}; ?>

<div>
    <x-portal.page-header :heading="__('Merchants')" :subheading="__('Review applications and control every merchant account: edit, suspend, ban, archive or delete.')" />

    <nav class="mb-4 flex gap-2 overflow-x-auto pb-1" aria-label="{{ __('Filter by status') }}">
        @foreach (['' => __('All'), ...collect(MerchantStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all(), 'archived' => __('In archive')] as $value => $label)
            <button type="button" wire:click="$set('status', '{{ $value }}')" wire:key="filter-{{ $value ?: 'all' }}"
                @class(['inline-flex min-h-9 shrink-0 items-center gap-2 rounded-full px-4 text-sm font-medium ring-1 transition', 'bg-ink-800 text-white ring-ink-800' => $status === $value, 'bg-white text-zinc-600 ring-zinc-200 hover:text-zinc-900' => $status !== $value])
                aria-pressed="{{ $status === $value ? 'true' : 'false' }}" data-test="merchant-filter-{{ $value ?: 'all' }}">
                {{ $label }}
                <span @class(['ltr-nums rounded-full px-1.5 text-xs', 'bg-white/15' => $status === $value, 'bg-zinc-100' => $status !== $value])>{{ $this->counts[$value] ?? 0 }}</span>
            </button>
        @endforeach
    </nav>

    <div class="surface mb-4 p-4">
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :label="__('Search')" :placeholder="__('Name, email, CR number or merchant ID')" />
    </div>

    <section class="surface transition-opacity" wire:loading.class.delay="opacity-60" wire:loading.attr.delay="aria-busy">
        @if ($this->merchants->isEmpty())
            <x-portal.empty icon="building-storefront" :heading="__('No merchants found')" />
        @else
            <div class="data-table">
                <flux:table :paginate="$this->merchants">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Merchant') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column class="max-md:hidden">{{ __('Activity') }}</flux:table.column>
                        <flux:table.column class="max-lg:hidden">{{ __('Team') }}</flux:table.column>
                        <flux:table.column class="max-sm:hidden">{{ __('Joined') }}</flux:table.column>
                        <flux:table.column><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->merchants as $merchant)
                            <flux:table.row :key="$merchant->public_id">
                                <flux:table.cell variant="strong">
                                    <a href="{{ route('admin.merchants.show', $merchant) }}" wire:navigate class="hover:underline">{{ $merchant->name }}</a>
                                    <span class="block text-xs font-normal text-zinc-500">{{ $merchant->legal_name }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($merchant->trashed())
                                        <flux:badge size="sm" color="zinc" icon="archive-box">{{ __('In archive') }}</flux:badge>
                                    @else
                                        <x-portal.status :status="$merchant->status" />
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="max-md:hidden">{{ $merchant->activityLabel() }}</flux:table.cell>
                                <flux:table.cell class="max-lg:hidden">{{ $merchant->users_count }}</flux:table.cell>
                                <flux:table.cell class="max-sm:hidden">{{ $merchant->created_at->locale(app()->getLocale())->translatedFormat('j M Y') }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions for :name', ['name' => $merchant->name])" data-test="merchant-actions-{{ $merchant->public_id }}" />
                                        <flux:menu>
                                            <flux:menu.item icon="pencil-square" :href="route('admin.merchants.show', $merchant)" wire:navigate>{{ __('Manage and edit') }}</flux:menu.item>
                                            <flux:menu.separator />
                                            @if ($merchant->trashed())
                                                <flux:menu.item icon="arrow-uturn-left" wire:click="restore('{{ $merchant->public_id }}')">{{ __('Restore') }}</flux:menu.item>
                                            @else
                                                @if ($merchant->status !== MerchantStatus::Active && $merchant->approved_at !== null)
                                                    <flux:menu.item icon="check-circle" x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Reactivate :name?', ['name' => $merchant->name])) }}, danger: false, run: () => $wire.reactivate('{{ $merchant->public_id }}') })">{{ __('Reactivate') }}</flux:menu.item>
                                                @endif
                                                @if ($merchant->status === MerchantStatus::Active)
                                                    <flux:menu.item icon="pause-circle" wire:click="askReason('{{ $merchant->public_id }}', 'suspended')">{{ __('Disable (suspend)') }}</flux:menu.item>
                                                @endif
                                                @if ($merchant->status !== MerchantStatus::Banned)
                                                    <flux:menu.item icon="no-symbol" variant="danger" wire:click="askReason('{{ $merchant->public_id }}', 'banned')" data-test="ban-merchant-{{ $merchant->public_id }}">{{ __('Ban') }}</flux:menu.item>
                                                @endif
                                                <flux:menu.item icon="archive-box" variant="danger" wire:click="askReason('{{ $merchant->public_id }}', 'archive')">{{ __('Archive (delete)') }}</flux:menu.item>
                                            @endif
                                            <flux:menu.item icon="trash" variant="danger" x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Delete :name permanently? This cannot be undone. Merchants with payment history can only be archived.', ['name' => $merchant->name])) }}, danger: true, run: () => $wire.destroy('{{ $merchant->public_id }}') })">{{ __('Delete permanently') }}</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </section>

    <flux:modal name="merchant-reason" class="w-full max-w-md">
        <form wire:submit="confirmWithReason" class="space-y-5">
            <flux:heading size="lg">
                @switch($targetStatus)
                    @case('banned') {{ __('Ban this merchant') }} @break
                    @case('archive') {{ __('Archive this merchant') }} @break
                    @default {{ __('Suspend this merchant') }}
                @endswitch
            </flux:heading>
            <flux:text>
                @switch($targetStatus)
                    @case('banned') {{ __('Every API key is revoked, payments stop in test and live mode, and the team loses access to the portal.') }} @break
                    @case('archive') {{ __('The merchant disappears from the portal and every API key stops working. Records are kept and you can restore it later.') }} @break
                    @default {{ __('Live keys are revoked and payments stop immediately.') }}
                @endswitch
            </flux:text>
            <flux:textarea wire:model="reason" :label="__('Reason shown to the merchant')" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger" data-test="confirm-merchant-reason">{{ __('Confirm') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
