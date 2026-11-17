@php
    $account = app()->bound(\App\Support\GatewayAccount::class) ? app(\App\Support\GatewayAccount::class) : null;
    $user = auth()->user();
    $merchant = $account?->merchant ?? $user->currentMerchant;
    $merchants = $user->merchants()->where('merchants.status', '!=', \App\Enums\MerchantStatus::Banned)->orderBy('name')->get(['merchants.id', 'merchants.public_id', 'merchants.name']);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas text-zinc-900 antialiased">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200/80 bg-white">
            <flux:sidebar.header>
                <a href="{{ $merchant ? route('dashboard') : ($user->isPlatformAdmin() ? route('admin.dashboard') : route('onboarding')) }}" class="flex items-center gap-2 px-1 text-lg font-semibold text-ink-900" wire:navigate>
                    <x-app-logo-icon class="size-7 text-ink-800" />
                    {{ config('app.name') }}
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            @if ($merchant)
                <flux:dropdown position="bottom" align="start">
                    <button type="button" class="flex w-full items-center gap-3 rounded-2xl bg-mint-50 p-2.5 text-start ring-1 ring-mint-200 transition hover:bg-mint-100" data-test="merchant-switcher">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-ink-800 text-sm font-semibold text-mint-400">{{ mb_substr($merchant->name, 0, 1) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-ink-900">{{ $merchant->name }}</span>
                            <span class="flex items-center gap-1.5 truncate text-xs text-zinc-500">
                                <span @class(['size-1.5 rounded-full', 'bg-mint-500' => $merchant->status->canProcessLivePayments(), 'bg-gold-500' => ! $merchant->status->canProcessLivePayments()])></span>
                                {{ $merchant->status->label() }}
                            </span>
                        </span>
                        <flux:icon.chevrons-up-down variant="micro" class="text-zinc-400" />
                    </button>
                    <flux:menu>
                        @foreach ($merchants as $item)
                            <form method="POST" action="{{ route('portal.merchant', $item->public_id) }}">
                                @csrf
                                <flux:menu.item as="button" type="submit" :icon="$item->id === $merchant->id ? 'check' : null" class="w-full">{{ $item->name }}</flux:menu.item>
                            </form>
                        @endforeach
                    </flux:menu>
                </flux:dropdown>
            @endif

            @if ($merchant)
                <flux:sidebar.nav>
                    <flux:sidebar.group class="grid">
                        <flux:sidebar.item icon="squares-2x2" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Overview') }}</flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('Payments')" class="grid">
                        <flux:sidebar.item icon="banknotes" :href="route('portal.payments')" :current="request()->routeIs('portal.payments*')" wire:navigate>{{ __('Transactions') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="shopping-cart" :href="route('portal.checkouts')" :current="request()->routeIs('portal.checkouts')" wire:navigate>{{ __('Checkout sessions') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="arrow-uturn-left" :href="route('portal.refunds')" :current="request()->routeIs('portal.refunds')" wire:navigate>{{ __('Refunds') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="users" :href="route('portal.customers')" :current="request()->routeIs('portal.customers*')" wire:navigate>{{ __('Customers') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="arrow-path" :href="route('portal.subscriptions')" :current="request()->routeIs('portal.subscriptions', 'portal.plans')" wire:navigate>{{ __('Subscriptions') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="link" :href="route('portal.links')" :current="request()->routeIs('portal.links*')" wire:navigate>{{ __('Payment links') }}</flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('Finance')" class="grid">
                        <flux:sidebar.item icon="scale" :href="route('portal.balance')" :current="request()->routeIs('portal.balance')" wire:navigate>{{ __('Balance') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="building-library" :href="route('portal.settlements')" :current="request()->routeIs('portal.settlements*')" wire:navigate>{{ __('Settlements') }}</flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('Developers')" class="grid">
                        <flux:sidebar.item icon="key" :href="route('portal.developers')" :current="request()->routeIs('portal.developers')" wire:navigate>{{ __('API keys') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="signal" :href="route('portal.webhooks')" :current="request()->routeIs('portal.webhooks')" wire:navigate>{{ __('Webhooks') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="book-open-text" :href="route('docs')" target="_blank">{{ __('API documentation') }}</flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('Account')" class="grid">
                        <flux:sidebar.item icon="building-storefront" :href="route('portal.business')" :current="request()->routeIs('portal.business')" wire:navigate>{{ __('Business profile') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="user-group" :href="route('portal.team')" :current="request()->routeIs('portal.team')" wire:navigate>{{ __('Team') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="cog-6-tooth" :href="route('profile.edit')" :current="request()->routeIs('profile.edit', 'security.edit', 'password.confirm')" wire:navigate data-test="portal-nav-settings">{{ __('Settings') }}</flux:sidebar.item>
                        @if ($user->isPlatformAdmin())
                            <flux:sidebar.item icon="shield-check" :href="route('admin.dashboard')">{{ __('Platform admin') }}</flux:sidebar.item>
                        @endif
                    </flux:sidebar.group>
                </flux:sidebar.nav>
            @else
                <flux:sidebar.nav>
                    @if ($user->isPlatformAdmin())
                        <flux:sidebar.item icon="shield-check" :href="route('admin.dashboard')">{{ __('Platform admin') }}</flux:sidebar.item>
                    @else
                        <flux:sidebar.item icon="building-storefront" :href="route('onboarding')" wire:navigate>{{ __('Set up your business') }}</flux:sidebar.item>
                    @endif
                    <flux:sidebar.item icon="cog-6-tooth" :href="route('profile.edit')" :current="request()->routeIs('profile.edit', 'security.edit', 'password.confirm')" wire:navigate>{{ __('Settings') }}</flux:sidebar.item>
                </flux:sidebar.nav>
            @endif

            <flux:spacer />

            <div class="rounded-2xl bg-ink-900 p-4 text-white">
                <p class="text-sm font-medium">{{ __('Need a hand with the integration?') }}</p>
                <p class="mt-1 text-xs text-white/70">{{ __('The API reference has copy-ready examples and test cards.') }}</p>
                <a href="{{ route('docs') }}" target="_blank" class="mt-3 inline-flex items-center gap-1 rounded-full bg-mint-400 px-3 py-1.5 text-xs font-medium text-ink-950">{{ __('Open the docs') }}</a>
            </div>

            <x-desktop-user-menu class="hidden lg:block" :name="$user->name" />
        </flux:sidebar>

        <flux:header class="sticky top-0 z-20 border-b border-zinc-200/80 bg-white/85 backdrop-blur">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <div class="flex items-center gap-2">
                <x-locale-switch class="hidden sm:block" />

                @if ($account)
                    <form method="POST" action="{{ route('portal.mode') }}" class="flex rounded-full bg-zinc-100 p-1 text-sm" role="radiogroup" aria-label="{{ __('Data mode') }}">
                        @csrf
                        <button type="submit" name="live" value="0" role="radio" aria-checked="{{ $account->livemode ? 'false' : 'true' }}"
                            @class(['min-h-9 rounded-full px-4 font-medium transition', 'bg-gold-300 text-zinc-900 shadow-sm' => ! $account->livemode, 'text-zinc-500 hover:text-zinc-900' => $account->livemode])
                            data-test="mode-test">{{ __('Test') }}</button>
                        <button type="submit" name="live" value="1" role="radio" aria-checked="{{ $account->livemode ? 'true' : 'false' }}"
                            @disabled(! $account->merchant->status->canProcessLivePayments())
                            title="{{ $account->merchant->status->canProcessLivePayments() ? __('Switch between test and live data') : __('Live mode opens once your account is approved') }}"
                            @class(['min-h-9 rounded-full px-4 font-medium transition disabled:cursor-not-allowed disabled:opacity-40', 'bg-ink-800 text-white shadow-sm' => $account->livemode, 'text-zinc-500 hover:text-zinc-900' => ! $account->livemode])
                            data-test="mode-toggle">{{ __('Live') }}</button>
                    </form>
                @endif

                <flux:dropdown position="top" align="end" class="lg:hidden">
                    <flux:profile :initials="$user->initials()" icon-trailing="chevron-down" />
                    <flux:menu>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                        <form method="POST" action="{{ route('locale.update') }}" class="w-full sm:hidden">
                            @csrf
                            <input type="hidden" name="locale" value="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}">
                            <flux:menu.item as="button" type="submit" icon="language" class="w-full">{{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}</flux:menu.item>
                        </form>
                        <flux:menu.separator />
                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">{{ __('Log out') }}</flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </flux:header>

        {{ $slot }}

        <x-confirm-dialog />

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts(['nonce' => \Illuminate\Support\Facades\Vite::cspNonce()])
    </body>
</html>
