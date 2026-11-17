@props(['code', 'title', 'message', 'icon' => 'exclamation-triangle'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
        <meta name="robots" content="noindex" />
        <meta name="theme-color" content="#5ce8a6" />
        <title>{{ $code }} · {{ $title }} · {{ config('app.name') }}</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @fonts
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-dvh bg-mint-400 p-2 text-zinc-900 antialiased sm:p-4 lg:p-6">
        <div class="relative flex min-h-[calc(100dvh-1rem)] flex-col overflow-hidden rounded-[1.75rem] bg-white sm:min-h-[calc(100dvh-2rem)] lg:min-h-[calc(100dvh-3rem)]">
            <div class="rings pointer-events-none absolute start-1/2 top-1/2 size-[60rem] -translate-x-1/2 -translate-y-1/2 opacity-80 rtl:translate-x-1/2" aria-hidden="true"></div>

            <header class="relative flex items-center justify-between px-5 py-5 sm:px-8">
                <a href="{{ url('/') }}" class="flex items-center gap-2 text-lg font-semibold text-ink-900">
                    <x-app-logo-icon class="size-7 text-ink-800" />
                    {{ config('app.name') }}
                </a>
            </header>

            <main class="relative mx-auto flex max-w-xl flex-1 flex-col items-center justify-center px-5 pb-16 text-center">
                <span class="pill-badge">
                    <flux:icon :name="$icon" variant="micro" />
                    <span>{{ __('Error :code', ['code' => "\u{2066}{$code}\u{2069}"]) }}</span>
                </span>
                <p class="ltr-nums mt-8 text-[7rem] leading-none font-medium tracking-tighter text-ink-900 sm:text-[9rem]" aria-hidden="true">{{ $code }}</p>
                <h1 class="display mt-6 text-3xl sm:text-4xl">{{ $title }}</h1>
                <p class="mt-4 text-zinc-600">{{ $message }}</p>
                <div class="mt-9 flex flex-col items-center gap-4 sm:flex-row">
                    <a href="{{ url('/') }}" class="btn-primary">{{ __('Back to the home page') }}</a>
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
