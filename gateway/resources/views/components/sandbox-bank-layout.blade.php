@props(['order', 'title'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
        <meta name="robots" content="noindex, nofollow" />
        <meta name="referrer" content="no-referrer" />
        <title>{{ $title }} · {{ __('Simulated bank') }}</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/checkout.js'])
    </head>
    <body class="min-h-dvh bg-slate-100 text-slate-900 antialiased">
        <div class="bg-gold-300 px-4 py-2 text-center text-sm font-medium" role="status">{{ __('Test environment. This page imitates a bank payment page and only accepts test cards.') }}</div>
        <header class="bg-slate-900 text-white">
            <div class="mx-auto flex max-w-md items-center justify-between px-4 py-4">
                <span class="flex items-center gap-2 font-semibold"><flux:icon.building-library variant="mini" /> {{ __('Simulated bank') }}</span>
                <span class="text-xs text-slate-300">{{ __('Secure payment') }}</span>
            </div>
        </header>
        <main class="mx-auto max-w-md px-4 py-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 border-b border-slate-200 pb-4 text-sm">
                    <dt class="text-slate-500">{{ __('Merchant') }}</dt>
                    <dd class="font-medium">{{ $order['description'] }}</dd>
                    <dt class="text-slate-500">{{ __('Amount') }}</dt>
                    <dd class="ltr-nums text-start font-semibold">{{ \App\Support\Money::format((int) $order['amount'], $order['currency']) }}</dd>
                </dl>
                <h1 class="mt-4 text-lg font-semibold">{{ $title }}</h1>
                {{ $slot }}
            </section>
        </main>
    </body>
</html>
