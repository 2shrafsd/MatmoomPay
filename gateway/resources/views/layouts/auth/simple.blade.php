<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-dvh bg-mint-400 p-2 antialiased sm:p-4 lg:p-6">
        <div class="grid min-h-[calc(100dvh-1rem)] overflow-hidden rounded-[1.75rem] bg-white sm:min-h-[calc(100dvh-2rem)] lg:min-h-[calc(100dvh-3rem)] lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)]">
            {{-- Form column --}}
            <div class="relative flex flex-col px-5 py-6 sm:px-10 lg:px-14">
                <div class="flex items-center justify-between">
                    <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-semibold text-ink-900" wire:navigate>
                        <x-app-logo-icon class="size-7 text-ink-800" />
                        {{ config('app.name') }}
                    </a>
                    <x-locale-switch />
                </div>

                <main class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center py-10">
                    {{ $slot }}
                </main>

                <p class="text-center text-xs text-zinc-400">© {{ now()->year }} {{ config('app.name') }} · {{ __('Secure payments in Sudanese pounds') }}</p>
            </div>

            {{-- Brand column --}}
            <aside class="relative m-3 hidden overflow-hidden rounded-[1.5rem] bg-mint-100 lg:block" aria-hidden="true">
                <img src="{{ asset('images/auth-customer.webp') }}" alt="" class="absolute inset-0 size-full object-cover">
                <div class="absolute inset-0 bg-linear-to-t from-ink-950/70 via-ink-950/10 to-transparent"></div>

                <div class="absolute end-6 top-6 inline-flex items-center gap-2 rounded-full bg-white/90 px-4 py-2 text-sm text-ink-900 backdrop-blur">
                    <span class="size-2 rounded-full bg-mint-500"></span>
                    {{ __('Secure payment gateway') }}
                </div>

                <div class="absolute inset-x-6 bottom-6 grid gap-3 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.85fr)]">
                    <div class="ink-card p-5">
                        <p class="font-medium">{{ __('Recent transactions') }}</p>
                        @foreach ([['#2610201031', __('Successful'), 18_500], ['#2610201030', __('Successful'), 7_250]] as [$ref, $status, $amount])
                            <div class="mt-3 flex items-center justify-between gap-3 border-t border-white/10 pt-3 text-xs">
                                <span class="ltr-nums font-mono text-mint-400">{{ $ref }}</span>
                                <span class="text-white/80">{{ $status }}</span>
                                <span class="ltr-nums text-white">{{ \App\Support\Money::format($amount) }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mint-card flex flex-col justify-between p-5">
                        <p class="text-sm">{{ __('Collected this month') }}</p>
                        <p class="ltr-nums mt-6 text-start text-2xl font-semibold">{{ \App\Support\Money::format(4_825_075) }}</p>
                    </div>
                </div>
            </aside>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts(['nonce' => \Illuminate\Support\Facades\Vite::cspNonce()])
    </body>
</html>
