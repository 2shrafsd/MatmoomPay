@props(['title' => null, 'merchant' => null, 'livemode' => true])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
        <meta name="robots" content="noindex, nofollow" />
        <meta name="referrer" content="no-referrer" />
        <meta name="theme-color" content="#5ce8a6" />
        <meta name="color-scheme" content="light" />
        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @fonts
        @vite(['resources/css/app.css', 'resources/js/checkout.js'])
    </head>
    <body class="min-h-dvh bg-mint-400 p-2 text-zinc-900 antialiased sm:p-4">
        <div class="mint-wash flex min-h-[calc(100dvh-1rem)] flex-col overflow-hidden rounded-[1.75rem] sm:min-h-[calc(100dvh-2rem)]">
            @unless ($livemode)
                <div class="flex items-center justify-center gap-2 bg-gold-300 px-4 py-2 text-center text-sm font-medium text-zinc-900" role="status">
                    <flux:icon.beaker variant="micro" />
                    {{ __('Test mode. No real money moves, and only test cards work here.') }}
                </div>
            @endunless

            <header class="mx-auto flex w-full max-w-5xl items-center justify-between gap-3 px-4 pt-5 pb-2 sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    @if ($merchant)
                        <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-ink-800 text-base font-semibold text-mint-400" aria-hidden="true">
                            {{ mb_substr($merchant->name, 0, 1) }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-base font-medium text-ink-900">{{ $merchant->name }}</p>
                            <p class="text-xs text-zinc-500">{{ $merchant->activityLabel() }}</p>
                        </div>
                    @else
                        <span class="flex items-center gap-2 font-semibold text-ink-900">
                            <x-app-logo-icon class="size-7 text-ink-800" />
                            {{ config('app.name') }}
                        </span>
                    @endif
                </div>
                <x-locale-switch />
            </header>

            <main class="mx-auto w-full max-w-5xl flex-1 px-4 pb-10 sm:px-6">
                {{ $slot }}
            </main>

            <footer class="mx-auto flex w-full max-w-5xl flex-wrap items-center justify-center gap-x-5 gap-y-2 px-4 pb-[max(1.5rem,env(safe-area-inset-bottom))] text-xs text-zinc-500">
                <span class="inline-flex items-center gap-1.5">
                    <span class="grid size-5 place-items-center rounded-full bg-ink-800 text-mint-400"><flux:icon.lock-closed variant="micro" class="size-3" /></span>
                    {{ __('Secured by :app', ['app' => config('app.name')]) }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="sr-only">{{ __('Accepted cards') }}:</span>
                    <span class="rounded-full bg-white px-2.5 py-0.5 font-semibold tracking-wide text-ink-900 ring-1 ring-zinc-200">VISA</span>
                    <span class="rounded-full bg-white px-2.5 py-0.5 font-semibold text-ink-900 ring-1 ring-zinc-200">Mastercard</span>
                </span>
                @if ($merchant)
                    {{-- Payers trust a page more when they can see who to ask; this also cuts chargebacks. --}}
                    <span class="inline-flex flex-wrap items-center justify-center gap-x-3 gap-y-1" data-test="merchant-contact">
                        <span>{{ __('Questions about this order? Contact :merchant', ['merchant' => $merchant->name]) }}</span>
                        <a href="mailto:{{ $merchant->email }}" class="ltr-nums underline underline-offset-2 hover:text-ink-900">{{ $merchant->email }}</a>
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $merchant->phone) }}" class="ltr-nums underline underline-offset-2 hover:text-ink-900">{{ $merchant->phone }}</a>
                        @if ($merchant->website)
                            <a href="{{ $merchant->website }}" rel="noopener noreferrer" target="_blank" class="ltr-nums underline underline-offset-2 hover:text-ink-900">{{ parse_url($merchant->website, PHP_URL_HOST) }}</a>
                        @endif
                    </span>
                @endif
            </footer>
        </div>
    </body>
</html>
