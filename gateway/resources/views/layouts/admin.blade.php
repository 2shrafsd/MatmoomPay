<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas text-zinc-900 antialiased">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200/80 bg-white">
            <flux:sidebar.header>
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 px-1 text-lg font-semibold text-ink-900" wire:navigate>
                    <x-app-logo-icon class="size-7 text-ink-800" />
                    {{ config('app.name') }}
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <div class="rounded-2xl bg-ink-900 px-4 py-3 text-white">
                <p class="text-xs text-mint-400">{{ __('Platform admin') }}</p>
                <p class="mt-0.5 truncate text-sm">{{ auth()->user()->name }}</p>
            </div>

            <flux:sidebar.nav>
                <flux:sidebar.group class="grid">
                    <flux:sidebar.item icon="chart-pie" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate>{{ __('Overview') }}</flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Manage')" class="grid">
                    <flux:sidebar.item icon="building-storefront" :href="route('admin.merchants')" :current="request()->routeIs('admin.merchants*')" wire:navigate>{{ __('Merchants') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="users" :href="route('admin.users')" :current="request()->routeIs('admin.users')" wire:navigate data-test="admin-nav-users">{{ __('Users') }}</flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Money')" class="grid">
                    <flux:sidebar.item icon="banknotes" :href="route('admin.payments')" :current="request()->routeIs('admin.payments')" wire:navigate>{{ __('Payments') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="building-library" :href="route('admin.settlements')" :current="request()->routeIs('admin.settlements')" wire:navigate>{{ __('Settlements') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="clipboard-document-list" :href="route('admin.audit')" :current="request()->routeIs('admin.audit')" wire:navigate>{{ __('Audit log') }}</flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('My account')" class="grid">
                    <flux:sidebar.item icon="user-circle" :href="route('profile.edit')" :current="request()->routeIs('profile.edit')" wire:navigate data-test="admin-nav-profile">{{ __('Profile') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="lock-closed" :href="route('security.edit')" :current="request()->routeIs('security.edit', 'password.confirm')" wire:navigate>{{ __('Security') }}</flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            @if (auth()->user()->merchants()->exists())
                <flux:sidebar.nav>
                    <flux:sidebar.item icon="arrow-uturn-left" :href="route('dashboard')">{{ __('Back to merchant portal') }}</flux:sidebar.item>
                </flux:sidebar.nav>
            @endif
            <div class="px-1"><x-locale-switch /></div>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <flux:header class="sticky top-0 z-20 border-b border-zinc-200/80 bg-white/85 backdrop-blur lg:hidden">
            <flux:sidebar.toggle icon="bars-2" inset="left" />
            <flux:spacer />
            <span class="text-sm font-medium text-ink-900">{{ __('Platform admin') }}</span>
            <flux:spacer />
            <flux:dropdown position="top" align="end">
                <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />
                <flux:menu>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    <flux:menu.separator />
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">{{ __('Log out') }}</flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        <flux:main class="mx-auto w-full max-w-7xl px-4! py-6! sm:px-6! lg:px-10! lg:py-8!">
            {{ $slot }}
        </flux:main>

        <x-confirm-dialog />

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts(['nonce' => \Illuminate\Support\Facades\Vite::cspNonce()])
    </body>
</html>
