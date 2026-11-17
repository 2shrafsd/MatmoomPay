<?php

use App\Actions\Audit\RecordAuditLog;
use App\Enums\CheckoutPaymentStatus;
use App\Livewire\PortalComponent;
use App\Models\PaymentLink;
use App\Support\Money;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('Payment link')] class extends PortalComponent {
    public PaymentLink $link;

    public function mount(PaymentLink $link): void
    {
        $this->authorize('view', $link);
        $this->ensureInAccount($link);
        $this->link = $link;
    }

    public function toggle(RecordAuditLog $recordAuditLog): void
    {
        $this->authorize('update', $this->link);
        $this->ensureInAccount($this->link);

        $this->link->update(['is_active' => ! $this->link->is_active]);
        $recordAuditLog->handle(Auth::user(), $this->link->is_active ? 'payment_link.opened' : 'payment_link.closed', $this->merchant(), $this->link);

        Flux::toast(text: $this->link->is_active ? __('The link is accepting payments again.') : __('The link is closed. Customers will see that it no longer accepts payments.'));
    }

    /**
     * SVG markup generated locally from our own URL; contains no user input.
     */
    #[Computed]
    public function qrSvg(): string
    {
        $renderer = new ImageRenderer(new RendererStyle(240, 1), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($this->link->url());
    }

    /**
     * A PNG copy for chat apps such as WhatsApp, which do not accept SVG images.
     */
    #[Computed]
    public function qrPng(): string
    {
        return base64_encode((new Writer(new GDLibRenderer(720, 2)))->writeString($this->link->url()));
    }

    public function with(): array
    {
        $paid = $this->link->checkoutSessions()->where('payment_status', CheckoutPaymentStatus::Paid);

        return [
            'collected' => (int) (clone $paid)->sum('total_amount'),
            'recent' => (clone $paid)->latest('paid_at')->limit(10)->get(),
            'canManage' => Auth::user()->can('update', $this->link),
        ];
    }
}; ?>

<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('portal.links')" wire:navigate>{{ __('Payment links') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $link->title }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <section class="surface p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <flux:heading size="xl" level="1" class="break-words">{{ $link->title }}</flux:heading>
                    <flux:text class="mt-1">{{ $link->amount ? Money::format($link->amount, $link->currency) : __('The customer enters the amount') }}</flux:text>
                </div>
                <flux:badge :color="$link->isAcceptingPayments() ? 'green' : 'zinc'">{{ $link->isAcceptingPayments() ? __('Active') : __('Closed') }}</flux:badge>
            </div>

            <div class="mt-6">
                <flux:label>{{ __('Share this link') }}</flux:label>
                <x-portal.copy :value="$link->url()" class="mt-2" />
            </div>

            <div class="mt-6 flex flex-wrap gap-2">
                <flux:button :href="'https://wa.me/?text='.rawurlencode($link->title.' '.$link->url())" target="_blank" icon="chat-bubble-left-right">{{ __('Send on WhatsApp') }}</flux:button>
                <flux:button :href="$link->url()" target="_blank" icon="arrow-top-right-on-square" variant="ghost">{{ __('Open link') }}</flux:button>
                @if ($canManage)
                    <flux:button wire:click="toggle" variant="ghost" :icon="$link->is_active ? 'pause' : 'play'">{{ $link->is_active ? __('Close link') : __('Reopen link') }}</flux:button>
                @endif
            </div>

            <dl class="mt-6 grid grid-cols-2 gap-4 border-t border-zinc-200 pt-6 text-sm sm:grid-cols-4">
                <div><dt class="text-zinc-500">{{ __('Collected') }}</dt><dd class="ltr-nums mt-0.5 text-start font-semibold">{{ Money::format($collected, $link->currency) }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Payments') }}</dt><dd class="mt-0.5 font-semibold">{{ $link->uses_count }}@if ($link->max_uses) / {{ $link->max_uses }}@endif</dd></div>
                <div><dt class="text-zinc-500">{{ __('Closes on') }}</dt><dd class="mt-0.5 font-semibold">{{ $link->expires_at?->locale(app()->getLocale())->translatedFormat('j M Y') ?? __('No end date') }}</dd></div>
                <div><dt class="text-zinc-500">{{ __('Created') }}</dt><dd class="mt-0.5 font-semibold">{{ $link->created_at->locale(app()->getLocale())->translatedFormat('j M Y') }}</dd></div>
            </dl>
        </section>

        <section class="surface flex flex-col items-center p-5 text-center" aria-labelledby="qr-heading">
            <flux:heading id="qr-heading">{{ __('QR code') }}</flux:heading>
            <flux:text class="mt-1 text-sm">{{ __('Print it at your counter so customers can scan and pay.') }}</flux:text>
            <div class="mt-4 rounded-2xl bg-white p-3 [&_svg]:size-48" role="img" aria-label="{{ __('QR code for :title', ['title' => $link->title]) }}">
                {!! $this->qrSvg !!}
            </div>
            <div class="mt-4 flex flex-wrap justify-center gap-2">
                <flux:button size="sm" icon="arrow-down-tray" :href="'data:image/png;base64,'.$this->qrPng" download="payment-link-{{ $link->public_id }}.png">{{ __('PNG for WhatsApp') }}</flux:button>
                <flux:button size="sm" variant="ghost" :href="'data:image/svg+xml;base64,'.base64_encode($this->qrSvg)" download="payment-link-{{ $link->public_id }}.svg">{{ __('SVG for print') }}</flux:button>
            </div>
        </section>
    </div>

    <section class="surface p-5 sm:p-6" aria-labelledby="link-payments">
        <flux:heading id="link-payments">{{ __('Latest payments') }}</flux:heading>
        @if ($recent->isEmpty())
            <flux:text class="mt-3">{{ __('Nobody has paid through this link yet.') }}</flux:text>
        @else
            <ul class="mt-3 divide-y divide-zinc-200 text-sm">
                @foreach ($recent as $session)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3" wire:key="ls-{{ $session->public_id }}">
                        <span class="font-medium">{{ $session->metadata['Customer name'] ?? __('Customer') }}</span>
                        <span class="flex items-center gap-4">
                            <span class="ltr-nums">{{ Money::format($session->total_amount, $session->currency) }}</span>
                            <span class="text-zinc-500">{{ $session->paid_at?->locale(app()->getLocale())->translatedFormat('j M · H:i') }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
