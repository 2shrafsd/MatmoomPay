<?php

use App\Actions\Merchants\RegisterMerchant;
use App\Data\MerchantProfileData;
use App\Support\BusinessActivities;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::onboarding'), Title('Set up your business')] class extends Component {
    public string $name = '';
    public string $legal_name = '';
    public string $activity = '';
    public string $commercial_registration = '';
    public string $email = '';
    public string $phone = '';
    public string $website = '';

    public function mount(): void
    {
        if (Auth::user()->merchants()->exists()) {
            $this->redirectRoute('dashboard', navigate: true);

            return;
        }

        $this->email = Auth::user()->email;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'legal_name' => ['required', 'string', 'max:160'],
            'activity' => ['required', Rule::in(array_keys(BusinessActivities::all()))],
            'commercial_registration' => ['nullable', 'string', 'max:40', 'regex:/^[0-9A-Za-z\-\/]+$/'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9 ]{8,16}$/'],
            'website' => ['nullable', 'url:https,http', 'max:255'],
        ];
    }

    public function save(RegisterMerchant $registerMerchant): void
    {
        if (Auth::user()->merchants()->exists()) {
            $this->redirectRoute('dashboard', navigate: true);

            return;
        }

        $validated = $this->validate();
        $validated['activity'] = BusinessActivities::all()[$validated['activity']];

        $registerMerchant->handle(Auth::user(), MerchantProfileData::fromArray($validated));

        $this->redirectRoute('dashboard', navigate: true);
    }
}; ?>

<div class="surface p-6 sm:p-8">
    <h1 class="display text-3xl sm:text-4xl">{{ __('Tell us about your business') }}</h1>
    <flux:text class="mt-3">{{ __('You can start testing payments as soon as you save. Our team reviews your details before live payments open, usually within two working days.') }}</flux:text>

    <form wire:submit="save" class="mt-8 grid gap-5 sm:grid-cols-2">
        <flux:input wire:model="name" :label="__('Business name')" :description="__('Shown to customers on the payment page.')" required class="sm:col-span-2" />
        <flux:input wire:model="legal_name" :label="__('Registered company name')" required class="sm:col-span-2" />

        <flux:select wire:model="activity" :label="__('Business activity')" :placeholder="__('Choose an activity')" required>
            @foreach (\App\Support\BusinessActivities::all() as $key => $label)
                <flux:select.option value="{{ $key }}">{{ __($label) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model="commercial_registration" :label="__('Commercial registration number')" dir="ltr" />

        <flux:input wire:model="email" type="email" :label="__('Business email')" dir="ltr" required />
        <flux:input wire:model="phone" type="tel" :label="__('Phone number')" placeholder="+249 91 234 5678" dir="ltr" required />

        <flux:input wire:model="website" type="url" :label="__('Website')" placeholder="https://" dir="ltr" class="sm:col-span-2" />

        <div class="sm:col-span-2">
            <flux:button type="submit" variant="primary" class="w-full sm:w-auto" data-test="onboarding-submit">{{ __('Create my business account') }}</flux:button>
        </div>
    </form>
</div>
