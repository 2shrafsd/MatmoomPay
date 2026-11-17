<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\MerchantStatus;
use App\Livewire\PortalComponent;
use App\Models\Payment;
use App\Queries\AccountMetrics;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('Overview')] class extends PortalComponent {
    #[Computed]
    public function metrics(): array
    {
        $stats = new AccountMetrics($this->account());
        $key = 'portal:metrics:'.$this->merchant()->id.':'.(int) $this->livemode();

        // Eight aggregates per load; a short cache keeps the dashboard fast without hiding new payments for long.
        // The cache only stores scalars (object unserialisation is disabled), so dates travel as strings.
        $metrics = Cache::remember($key, 30, fn (): array => [
            'today' => $stats->volume(now()->startOfDay()),
            'month' => $stats->volume(now()->subDays(30)),
            'count' => $stats->successfulCount(now()->subDays(30)),
            'rate' => $stats->successRate(now()->subDays(30)),
            'refunded' => $stats->refunded(now()->subDays(30)),
            'balance' => $stats->availableBalance(),
            'series' => array_map(fn (array $day): array => ['date' => $day['date']->toDateString(), 'amount' => $day['amount']], $stats->dailyVolume(14)),
        ]);

        $metrics['series'] = array_map(fn (array $day): array => ['date' => CarbonImmutable::parse($day['date']), 'amount' => $day['amount']], $metrics['series']);

        return $metrics;
    }

    /**
     * @return Collection<int, Payment>
     */
    #[Computed]
    public function recentPayments(): Collection
    {
        return Payment::query()
            ->forAccount($this->merchant(), $this->livemode())
            ->with('checkoutSession:id,invoice')
            ->latest('id')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function merchantStatus(): MerchantStatus
    {
        return $this->merchant()->status;
    }

    /**
     * What is left before live payments open, in the order the merchant should do it.
     *
     * @return list<array{label: string, done: bool, url: string|null}>
     */
    #[Computed]
    public function activationSteps(): array
    {
        $merchant = $this->merchant();
        $uploaded = $merchant->documents()->whereIn('type', DocumentType::required())->where('status', '!=', DocumentStatus::Rejected)->distinct()->count('type');

        return [
            ['label' => __('Create your business'), 'done' => true, 'url' => null],
            ['label' => __('Upload the verification documents'), 'done' => $uploaded >= count(DocumentType::required()), 'url' => route('portal.business')],
            ['label' => __('Add the bank account for payouts'), 'done' => filled($merchant->settlement_iban), 'url' => route('portal.business')],
            ['label' => __('Approval by our team'), 'done' => $merchant->status === MerchantStatus::Active, 'url' => null],
        ];
    }

    #[Computed]
    public function merchantName(): string
    {
        return $this->merchant()->name;
    }
}; ?>

@php
    $metrics = $this->metrics;
    $peak = max(1, max(array_column($metrics['series'], 'amount')));
@endphp

<div class="space-y-6">
    <x-portal.page-header :eyebrow="now()->locale(app()->getLocale())->translatedFormat('l j F')" :heading="__('Welcome back, :name', ['name' => \Illuminate\Support\Str::before(auth()->user()->name, ' ')])" :subheading="$this->merchantName">
        <x-slot:actions>
            <flux:button :href="route('portal.links')" icon="link" wire:navigate>{{ __('New payment link') }}</flux:button>
            <flux:button :href="route('docs')" icon="code-bracket" variant="primary" target="_blank">{{ __('Integrate') }}</flux:button>
        </x-slot:actions>
    </x-portal.page-header>

    @if ($this->merchantStatus === MerchantStatus::Pending)
        <section class="rounded-[1.5rem] border border-gold-300 bg-gold-100 p-5" aria-labelledby="activation-heading">
            <div class="flex items-start gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-gold-300"><flux:icon.clock variant="mini" /></span>
                <div>
                    <h2 id="activation-heading" class="font-medium text-zinc-900">{{ __('Open live payments in four steps') }}</h2>
                    <p class="text-sm text-zinc-700">{{ __('Test mode is ready to use with your test keys. We will email you as soon as live payments are switched on.') }}</p>
                </div>
            </div>
            <ol class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4" data-test="activation-steps">
                @foreach ($this->activationSteps as $step)
                    <li class="flex items-center gap-2 rounded-2xl bg-white/70 p-3 text-sm">
                        <flux:icon :name="$step['done'] ? 'check-circle' : 'ellipsis-horizontal-circle'" variant="mini" @class(['text-mint-600' => $step['done'], 'text-zinc-400' => ! $step['done']]) />
                        @if (! $step['done'] && $step['url'])
                            <a href="{{ $step['url'] }}" wire:navigate class="font-medium text-ink-900 underline underline-offset-4">{{ $step['label'] }}</a>
                        @else
                            <span @class(['text-zinc-500 line-through decoration-zinc-300' => $step['done'], 'text-zinc-700' => ! $step['done']])>{{ $step['label'] }}</span>
                        @endif
                        <span class="sr-only">{{ $step['done'] ? __('Done') : __('Not done yet') }}</span>
                    </li>
                @endforeach
            </ol>
        </section>
    @elseif ($this->merchantStatus === MerchantStatus::Suspended)
        <div class="flex items-start gap-3 rounded-[1.5rem] border border-red-200 bg-red-50 p-4">
            <span class="grid size-9 shrink-0 place-items-center rounded-full bg-red-100 text-red-700"><flux:icon.no-symbol variant="mini" /></span>
            <div><p class="font-medium text-red-900">{{ __('Your account is suspended') }}</p><p class="text-sm text-red-800">{{ __('Payments are paused. Contact support to resolve the issue.') }} <a href="mailto:{{ config('gateway.support_email') }}" class="ltr-nums font-medium underline underline-offset-2">{{ config('gateway.support_email') }}</a></p></div>
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        {{-- Balance: the reference's dark data card --}}
        <section class="ink-card relative overflow-hidden p-6 sm:p-8" aria-labelledby="balance-heading">
            <div class="rings pointer-events-none absolute -end-40 -top-40 size-[30rem] opacity-[0.07] invert" aria-hidden="true"></div>
            <div class="relative flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p id="balance-heading" class="text-sm text-white/70">{{ __('Available balance') }}</p>
                    <p class="ltr-nums mt-2 text-start text-4xl font-medium tracking-tight text-white sm:text-5xl">{{ Money::format($metrics['balance']) }}</p>
                </div>
                <span class="rounded-full bg-white/10 px-3 py-1.5 text-xs text-white/80">{{ __('Last 14 days') }}</span>
            </div>
            <div class="relative mt-8 flex h-28 items-end gap-1.5 sm:gap-2" aria-hidden="true">
                @foreach ($metrics['series'] as $day)
                    <div class="group relative flex h-full flex-1 flex-col justify-end" wire:key="day-{{ $day['date']->toDateString() }}">
                        <div class="rounded-full bg-mint-400/85 transition group-hover:bg-mint-300" style="height: {{ max(6, round($day['amount'] / $peak * 100)) }}%"></div>
                        <span class="pointer-events-none absolute -top-8 start-1/2 z-10 hidden -translate-x-1/2 rounded-full bg-white px-2 py-0.5 text-xs whitespace-nowrap text-ink-900 group-hover:block rtl:translate-x-1/2"><span class="ltr-nums">{{ Money::decimal($day['amount']) }}</span></span>
                    </div>
                @endforeach
            </div>
            <table class="sr-only">
                <caption>{{ __('Successful payment volume for each of the last 14 days') }}</caption>
                <tbody>
                    @foreach ($metrics['series'] as $day)
                        <tr><th scope="row">{{ $day['date']->locale(app()->getLocale())->translatedFormat('j F') }}</th><td>{{ Money::format($day['amount']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <dl class="relative mt-6 grid grid-cols-2 gap-4 border-t border-white/10 pt-5 text-sm">
                <div><dt class="text-white/60">{{ __('Collected today') }}</dt><dd class="ltr-nums mt-1 text-start text-lg font-medium">{{ Money::format($metrics['today']) }}</dd></div>
                <div><dt class="text-white/60">{{ __('Last 30 days') }}</dt><dd class="ltr-nums mt-1 text-start text-lg font-medium">{{ Money::format($metrics['month']) }}</dd></div>
            </dl>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
            <section class="mint-card flex flex-col justify-between p-6" aria-labelledby="rate-heading">
                <div class="flex items-center justify-between">
                    <p id="rate-heading" class="text-sm text-ink-900/75">{{ __('Success rate') }}</p>
                    <span class="rounded-full bg-white/70 px-3 py-1 text-xs text-ink-900">{{ __('Last 30 days') }}</span>
                </div>
                <p class="ltr-nums mt-6 text-start text-5xl font-medium tracking-tight">{{ $metrics['rate'] === null ? '—' : $metrics['rate'].'%' }}</p>
                <p class="mt-2 text-sm text-ink-900/75">{{ trans_choice(':count successful payment|:count successful payments', $metrics['count'], ['count' => $metrics['count']]) }}</p>
            </section>
            <section class="surface flex flex-col justify-between p-6" aria-labelledby="refund-heading">
                <div class="flex items-center justify-between">
                    <p id="refund-heading" class="text-sm text-zinc-500">{{ __('Refunds') }}</p>
                    <span class="grid size-9 place-items-center rounded-full bg-mint-100 text-ink-800"><flux:icon.arrow-uturn-left variant="mini" /></span>
                </div>
                <p class="ltr-nums mt-6 text-start text-3xl font-medium tracking-tight text-ink-900">{{ Money::format($metrics['refunded']) }}</p>
                <p class="mt-2 text-sm text-zinc-500">{{ __('Last 30 days') }}</p>
            </section>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <section class="surface overflow-hidden" aria-labelledby="recent-heading">
            <div class="flex items-center justify-between p-5 sm:p-6">
                <h2 id="recent-heading" class="text-lg font-medium text-ink-900">{{ __('Recent transactions') }}</h2>
                <a href="{{ route('portal.payments') }}" wire:navigate class="rounded-full bg-mint-100 px-3 py-1.5 text-sm font-medium text-ink-900 hover:bg-mint-200">{{ __('View all') }}</a>
            </div>

            @if ($this->recentPayments->isEmpty())
                <x-portal.empty icon="banknotes" :heading="__('No transactions yet')" :body="__('Payments appear here as soon as a customer pays a checkout session or payment link.')" />
            @else
                <ul class="divide-y divide-zinc-100 border-t border-zinc-100">
                    @foreach ($this->recentPayments as $payment)
                        <li wire:key="recent-{{ $payment->public_id }}">
                            <a href="{{ route('portal.payments.show', $payment) }}" wire:navigate class="flex items-center gap-4 px-5 py-3.5 transition hover:bg-mint-50 sm:px-6">
                                <span @class(['grid size-10 shrink-0 place-items-center rounded-full', 'bg-mint-100 text-ink-800' => $payment->status->value === 'Successful', 'bg-red-50 text-red-600' => $payment->status->value === 'Failed', 'bg-gold-100 text-gold-700' => $payment->status->value === 'InProcess'])>
                                    <flux:icon :name="$payment->status->value === 'Failed' ? 'x-mark' : ($payment->status->value === 'Successful' ? 'arrow-down-left' : 'clock')" variant="mini" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="ltr-nums block truncate text-start font-mono text-xs text-ink-600">#{{ $payment->checkoutSession?->invoice ?? $payment->public_id }}</span>
                                    <span class="block truncate text-sm text-zinc-600"><span class="ltr-nums">{{ $payment->card_brand->value }} •••• {{ $payment->card_last_four }}</span> · {{ $payment->created_at->locale(app()->getLocale())->translatedFormat('j M · H:i') }}</span>
                                </span>
                                <span class="text-end">
                                    <span class="ltr-nums block font-medium text-ink-900">{{ Money::format($payment->amount, $payment->currency) }}</span>
                                    <x-portal.status :status="$payment->status" class="mt-1" />
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="surface p-5 sm:p-6" aria-labelledby="start-heading">
            <h2 id="start-heading" class="text-lg font-medium text-ink-900">{{ __('Get paid your way') }}</h2>
            <ul class="mt-4 space-y-2">
                @foreach ([
                    ['link', route('portal.links'), __('Share a payment link'), __('Send it on WhatsApp or show it as a QR code. No website needed.')],
                    ['code-bracket', route('portal.developers'), __('Add checkout to your site'), __('Create a session with one API call and redirect your customer.')],
                    ['arrow-path', route('portal.plans'), __('Sell subscriptions'), __('Charge saved cards every month or every year.')],
                    ['signal', route('portal.webhooks'), __('Receive payment notifications'), __('Set a webhook URL and we will call it for every payment.')],
                ] as [$icon, $href, $title, $body])
                    <li>
                        <a href="{{ $href }}" wire:navigate class="flex items-start gap-3 rounded-2xl p-3 transition hover:bg-mint-50">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-ink-800 text-mint-400"><flux:icon :name="$icon" variant="mini" /></span>
                            <span><span class="block font-medium text-ink-900">{{ $title }}</span><span class="text-sm text-zinc-500">{{ $body }}</span></span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</div>
