<?php

use App\Enums\MerchantStatus;
use App\Enums\PaymentStatus;
use App\Models\BalanceTransaction;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin'), Title('Platform overview')] class extends Component {
    /**
     * Platform-wide totals scan large tables, so they are computed once a minute rather than on every click.
     */
    #[Computed]
    public function stats(): array
    {
        return Cache::remember('admin:platform-stats', 60, function (): array {
            $live = Payment::query()->where('livemode', true)->where('status', PaymentStatus::Successful)->where('completed_at', '>=', now()->subDays(30));
            $totals = (clone $live)->selectRaw('count(*) as payments, coalesce(sum(amount), 0) as volume, coalesce(sum(fee), 0) as fees')->first();

            return [
                'volume' => (int) $totals?->getAttribute('volume'),
                'fees' => (int) $totals?->getAttribute('fees'),
                'payments' => (int) $totals?->getAttribute('payments'),
                'unsettled' => (int) BalanceTransaction::query()->where('livemode', true)->whereNull('settlement_id')->sum('amount'),
                'byStatus' => Merchant::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all(),
            ];
        });
    }

    /**
     * Counts behind the owner's control shortcuts. They are cheap indexed counts, so they are always fresh.
     *
     * @return array{users: int, bannedUsers: int, admins: int, archived: int, banned: int}
     */
    #[Computed]
    public function control(): array
    {
        return [
            'users' => User::query()->count(),
            'bannedUsers' => User::query()->whereNotNull('banned_at')->count(),
            'admins' => User::query()->where('is_platform_admin', true)->count(),
            'archived' => Merchant::onlyTrashed()->count(),
            'banned' => Merchant::query()->where('status', MerchantStatus::Banned)->count(),
        ];
    }

    #[Computed]
    public function pendingMerchants()
    {
        return Merchant::query()->where('status', MerchantStatus::Pending)->oldest()->limit(10)->get();
    }
}; ?>

<div>
    <x-portal.page-header :heading="__('Platform overview')" :subheading="__('Live activity across all merchants.')" />

    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-portal.stat :label="__('Live volume, 30 days')" :value="Money::format($this->stats['volume'])" icon="chart-bar" />
        <x-portal.stat :label="__('Fees earned, 30 days')" :value="Money::format($this->stats['fees'])" icon="receipt-percent" />
        <x-portal.stat :label="__('Live payments, 30 days')" :value="number_format($this->stats['payments'])" icon="credit-card" />
        <x-portal.stat :label="__('Owed to merchants')" :value="Money::format($this->stats['unsettled'])" icon="building-library" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <section class="surface" aria-labelledby="review-heading">
            <div class="flex items-center justify-between p-5 pb-2">
                <flux:heading id="review-heading">{{ __('Waiting for review') }}</flux:heading>
                <flux:link :href="route('admin.merchants', ['status' => 'pending'])" wire:navigate class="text-sm">{{ __('View all') }}</flux:link>
            </div>
            @if ($this->pendingMerchants->isEmpty())
                <x-portal.empty icon="check-badge" :heading="__('Nothing to review')" :body="__('New merchant applications will appear here.')" />
            @else
                <ul class="divide-y divide-zinc-200">
                    @foreach ($this->pendingMerchants as $merchant)
                        <li wire:key="pm-{{ $merchant->id }}">
                            <a href="{{ route('admin.merchants.show', $merchant) }}" wire:navigate class="flex items-center justify-between gap-3 px-5 py-3 transition hover:bg-zinc-50">
                                <span class="min-w-0">
                                    <span class="block truncate font-medium">{{ $merchant->name }}</span>
                                    <span class="block truncate text-sm text-zinc-500">{{ $merchant->legal_name }} · {{ $merchant->activityLabel() }}</span>
                                </span>
                                <span class="shrink-0 text-sm text-zinc-500">{{ $merchant->created_at->locale(app()->getLocale())->diffForHumans() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="surface p-5" aria-labelledby="status-heading">
            <flux:heading id="status-heading">{{ __('Merchants by status') }}</flux:heading>
            <dl class="mt-4 space-y-3 text-sm">
                @foreach (\App\Enums\MerchantStatus::cases() as $status)
                    <div class="flex items-center justify-between">
                        <dt><flux:badge size="sm" :color="$status->color()">{{ $status->label() }}</flux:badge></dt>
                        <dd class="font-semibold">{{ $this->stats['byStatus'][$status->value] ?? 0 }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>

    <section class="mt-6" aria-labelledby="control-heading">
        <flux:heading id="control-heading" class="mb-3">{{ __('Owner controls') }}</flux:heading>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 2xl:grid-cols-5">
            @foreach ([
                ['label' => __('All users'), 'value' => $this->control['users'], 'icon' => 'users', 'href' => route('admin.users')],
                ['label' => __('Platform admins'), 'value' => $this->control['admins'], 'icon' => 'shield-check', 'href' => route('admin.users', ['filter' => 'admins'])],
                ['label' => __('Banned users'), 'value' => $this->control['bannedUsers'], 'icon' => 'no-symbol', 'href' => route('admin.users', ['filter' => 'banned'])],
                ['label' => __('Banned merchants'), 'value' => $this->control['banned'], 'icon' => 'building-storefront', 'href' => route('admin.merchants', ['status' => 'banned'])],
                ['label' => __('Archived merchants'), 'value' => $this->control['archived'], 'icon' => 'archive-box', 'href' => route('admin.merchants', ['status' => 'archived'])],
            ] as $card)
                <a href="{{ $card['href'] }}" wire:navigate class="surface group flex items-center gap-3 p-4 transition hover:ring-mint-300" wire:key="control-{{ $loop->index }}">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-mint-50 text-ink-800 ring-1 ring-mint-200"><flux:icon :name="$card['icon']" variant="mini" /></span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm text-zinc-500">{{ $card['label'] }}</span>
                        <span class="ltr-nums block text-start text-xl font-semibold text-ink-900">{{ number_format($card['value']) }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>
</div>
