<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\TeamNotification;
use App\Notifications\EmailAddressChanged;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::panel'), Title('Profile settings')] class extends Component {
    use PasswordValidationRules, ProfileValidationRules;

    public string $name = '';
    public string $email = '';
    public string $current_password = '';

    /**
     * @var array<string, bool>
     */
    public array $notifications = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;

        foreach (TeamNotification::cases() as $type) {
            $this->notifications[$type->value] = Auth::user()->wantsNotification($type);
        }
    }

    /**
     * Saves which business emails this person receives. Only known types are kept.
     */
    public function saveNotificationPreferences(): void
    {
        $preferences = [];

        foreach (TeamNotification::cases() as $type) {
            $preferences[$type->value] = (bool) ($this->notifications[$type->value] ?? false);
        }

        Auth::user()->forceFill(['notification_preferences' => $preferences])->save();

        Flux::toast(variant: 'success', text: __('Email preferences saved.'));
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $rules = $this->profileRules($user->id);
        $changesEmail = Str::lower(trim($this->email)) !== Str::lower($user->email);

        // The email is the password-recovery channel, so changing it needs the current password.
        if ($changesEmail) {
            $rules['current_password'] = $this->currentPasswordRules();
        }

        try {
            $validated = $this->validate($rules);
        } finally {
            $this->reset('current_password');
        }

        unset($validated['current_password']);

        $previousEmail = $user->email;

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($user->wasChanged('email')) {
            Notification::route('mail', $previousEmail)->notify(new EmailAddressChanged($user->email));
        }

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        // Same budget as Fortify's verification route, plus a per-address cap so one account cannot flood an inbox.
        $sent = RateLimiter::attempt('verification-mail:'.$user->id, 6, fn () => true, 60)
            && RateLimiter::attempt('verification-mail-to:'.sha1(Str::lower($user->email)), 10, fn () => true, 3600);

        if (! $sent) {
            Flux::toast(variant: 'danger', text: __('Too many verification emails. Please wait a while and try again.'));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium ! !text-mint-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <flux:input
                wire:model="current_password"
                :label="__('Current password')"
                :description="__('Required only when you change your email address.')"
                type="password"
                autocomplete="current-password"
                viewable
            />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

            </div>
        </form>

        <form wire:submit="saveNotificationPreferences" class="mt-12 space-y-4" aria-labelledby="notifications-heading">
            <flux:heading id="notifications-heading">{{ __('Email notifications') }}</flux:heading>
            <flux:subheading>{{ __('Choose which business events reach your inbox. Each email only goes to roles that can act on it.') }}</flux:subheading>
            @foreach (TeamNotification::cases() as $type)
                <flux:checkbox wire:model="notifications.{{ $type->value }}" :label="$type->label()" data-test="notification-{{ $type->value }}" />
            @endforeach
            <flux:button type="submit" variant="primary">{{ __('Save email preferences') }}</flux:button>
        </form>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
