<?php

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin'), Title('Audit log')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $action = '';

    public function updatedAction(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, AuditLog>
     */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        $action = preg_replace('/[^a-z_.]/', '', mb_strtolower($this->action)) ?? '';

        return AuditLog::query()
            ->with(['actor:id,name,email', 'merchant:id,name,public_id'])
            ->when($action !== '', fn (Builder $query) => $query->where('action', 'like', $action.'%'))
            ->latest('id')
            ->paginate(30);
    }
}; ?>

<div>
    <x-portal.page-header :heading="__('Audit log')" :subheading="__('Security-relevant actions by merchants and admins. Entries cannot be edited or deleted.')" />

    <div class="surface mb-4 p-4">
        <flux:input wire:model.live.debounce.400ms="action" icon="funnel" :label="__('Filter by action')" placeholder="merchant.status_changed" dir="ltr" />
    </div>

    <section class="surface transition-opacity" wire:loading.class.delay="opacity-60" wire:loading.attr.delay="aria-busy">
        @if ($this->entries->isEmpty())
            <x-portal.empty icon="clipboard-document-list" :heading="__('No entries')" />
        @else
            <div class="data-table">
                <flux:table :paginate="$this->entries">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Action') }}</flux:table.column>
                        <flux:table.column>{{ __('By') }}</flux:table.column>
                        <flux:table.column class="max-md:hidden">{{ __('Merchant') }}</flux:table.column>
                        <flux:table.column class="max-lg:hidden">{{ __('Details') }}</flux:table.column>
                        <flux:table.column class="max-lg:hidden">{{ __('IP address') }}</flux:table.column>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->entries as $entry)
                            <flux:table.row :key="'audit-'.$entry->id">
                                <flux:table.cell variant="strong"><code class="font-mono text-xs" dir="ltr">{{ $entry->action }}</code></flux:table.cell>
                                <flux:table.cell>{{ $entry->actor?->name ?? __('System') }}</flux:table.cell>
                                <flux:table.cell class="max-md:hidden">{{ $entry->merchant?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell class="max-lg:hidden">
                                    <span class="ltr-nums font-mono text-xs break-all" dir="ltr">{{ $entry->properties ? \Illuminate\Support\Str::limit(json_encode($entry->properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 80) : '—' }}</span>
                                </flux:table.cell>
                                <flux:table.cell class="max-lg:hidden"><span class="ltr-nums">{{ $entry->ip_address ?? '—' }}</span></flux:table.cell>
                                <flux:table.cell>{{ $entry->created_at->locale(app()->getLocale())->translatedFormat('j M Y · H:i') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </section>
</div>
