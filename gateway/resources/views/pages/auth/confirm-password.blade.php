<x-layouts::panel :title="__('Confirm password')">
    <div class="mx-auto mt-4 flex w-full max-w-md flex-col gap-6 surface p-6 sm:mt-10 sm:p-8" data-test="confirm-password-panel">
        <x-auth-header
            :title="__('Confirm password')"
            :description="__('This is a secure area of the application. Please confirm your password before continuing.')"
        />

        <div class="flex items-center gap-3 rounded-2xl bg-mint-50 p-3 ring-1 ring-mint-200">
            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-white text-ink-800"><flux:icon.lock-closed variant="mini" /></span>
            <flux:text class="text-sm">{{ __('You stay in your dashboard. Confirm once and you will not be asked again for a while.') }}</flux:text>
        </div>

        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify
            options-route="passkey.confirm-options"
            submit-route="passkey.confirm"
            :label="__('Confirm with passkey')"
            :loading-label="__('Confirming...')"
            :separator="__('Or confirm with password')"
        />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Password')"
                viewable
            />

            <div class="flex flex-col-reverse gap-2 sm:flex-row">
                <flux:button :href="url()->previous() !== url()->current() ? url()->previous() : route('profile.edit')" variant="ghost" class="w-full sm:w-auto">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit" class="w-full sm:flex-1" data-test="confirm-password-button">
                    {{ __('Confirm') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::panel>
