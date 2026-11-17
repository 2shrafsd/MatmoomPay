<?php

use App\Concerns\PasswordValidationRules;
use App\Enums\MerchantRole;
use App\Models\Merchant;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        // The password check here must not become an unthrottled way to guess the account password.
        $key = 'delete-account-password:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'password' => __('You tried this too many times in a short while. Wait a minute, then try again.'),
            ]);
        }

        RateLimiter::hit($key, 60);

        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        // A business must never be left without an owner who can manage payouts and the team.
        $soleOwnerOf = Auth::user()->merchants()
            ->wherePivot('role', MerchantRole::Owner->value)
            ->get()
            ->first(fn (Merchant $merchant): bool => $merchant->users()->wherePivot('role', MerchantRole::Owner->value)->count() === 1);

        if ($soleOwnerOf !== null) {
            throw ValidationException::withMessages([
                'password' => __('You are the only owner of :merchant. Make another team member an owner before deleting your account.', ['merchant' => $soleOwnerOf->name]),
            ]);
        }

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form method="POST" wire:submit="deleteUser" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Are you sure you want to delete your account?') }}</flux:heading>

            <flux:subheading>
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </flux:subheading>
        </div>

        <flux:input wire:model="password" :label="__('Password')" type="password" viewable />

        <div class="flex justify-end space-x-2">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" type="submit" data-test="confirm-delete-user-button">
                {{ __('Delete account') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
