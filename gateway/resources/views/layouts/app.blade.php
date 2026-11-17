@php($account = app()->bound(\App\Support\GatewayAccount::class) ? app(\App\Support\GatewayAccount::class) : null)

<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="mx-auto w-full max-w-7xl px-4! py-6! sm:px-6! lg:px-10! lg:py-8!">
        @if ($account && ! $account->livemode)
            <div class="mb-6 flex items-center gap-3 rounded-full border border-gold-300 bg-gold-100 px-4 py-2 text-sm text-zinc-800" role="status" data-test="test-mode-banner">
                <span class="grid size-6 place-items-center rounded-full bg-gold-300"><flux:icon.beaker variant="micro" /></span>
                {{ __('You are viewing test data. Test payments never move real money.') }}
            </div>
        @endif

        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
