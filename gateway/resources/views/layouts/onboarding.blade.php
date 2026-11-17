<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-dvh bg-mint-400 p-2 antialiased sm:p-4 lg:p-6">
        <div class="mint-wash min-h-[calc(100dvh-1rem)] rounded-[1.75rem] sm:min-h-[calc(100dvh-2rem)] lg:min-h-[calc(100dvh-3rem)]">
            <div class="mx-auto flex w-full max-w-2xl flex-col px-4 py-6 sm:px-6">
                <div class="mb-10 flex items-center justify-between">
                    <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-semibold text-ink-900">
                        <x-app-logo-icon class="size-7 text-ink-800" />
                        {{ config('app.name') }}
                    </a>
                    <div class="flex items-center gap-2">
                        <x-locale-switch />
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn-ghost" data-test="onboarding-logout">{{ __('Log out') }}</button>
                        </form>
                    </div>
                </div>
                <span class="pill-badge mb-5 self-start">{{ __('Business details') }}</span>
                <p class="mb-6 text-sm text-ink-800">{{ __('Next: test mode opens right away. Live payments open after you upload your documents, add a bank account and our team approves the account.') }}</p>
                {{ $slot }}
            </div>
        </div>

        @fluxScripts(['nonce' => \Illuminate\Support\Facades\Vite::cspNonce()])
    </body>
</html>
