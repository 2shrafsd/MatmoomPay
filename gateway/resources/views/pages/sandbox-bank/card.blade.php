<x-sandbox-bank-layout :order="$order" :title="__('Enter your card details')">
    <form method="POST" action="{{ route('sandbox-bank.card') }}" class="mt-4 space-y-4" data-submit-once novalidate>
        @csrf
        <input type="hidden" name="p" value="{{ $p }}">
        <input type="hidden" name="s" value="{{ $s }}">

        <div>
            <label for="card_number" class="mb-1.5 block text-sm font-medium">{{ __('Card number') }}</label>
            <input id="card_number" name="card_number" type="text" inputmode="numeric" autocomplete="cc-number" dir="ltr" maxlength="23" data-format="card" required class="field-input ltr-nums" @error('card_number') aria-invalid="true" @enderror>
            @error('card_number') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="expiry" class="mb-1.5 block text-sm font-medium">{{ __('Expiry date') }}</label>
                <input id="expiry" name="expiry" type="text" inputmode="numeric" autocomplete="cc-exp" dir="ltr" maxlength="5" placeholder="MM/YY" data-format="expiry" required class="field-input ltr-nums" @error('expiry') aria-invalid="true" @enderror>
                @error('expiry') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="cvc" class="mb-1.5 block text-sm font-medium">{{ __('Security code') }}</label>
                <input id="cvc" name="cvc" type="password" inputmode="numeric" autocomplete="cc-csc" dir="ltr" maxlength="4" data-format="digits" required class="field-input ltr-nums" @error('cvc') aria-invalid="true" @enderror>
                @error('cvc') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label for="cardholder_name" class="mb-1.5 block text-sm font-medium">{{ __('Name on card') }}</label>
            <input id="cardholder_name" name="cardholder_name" type="text" autocomplete="cc-name" dir="ltr" maxlength="60" required class="field-input" @error('cardholder_name') aria-invalid="true" @enderror>
            @error('cardholder_name') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn-primary w-full" data-busy-label="{{ __('Processing…') }}">{{ __('Continue') }}</button>
    </form>
    <div class="mt-3 text-center">
        <a href="{{ route('sandbox-bank.cancel', ['p' => $p, 's' => $s]) }}" class="btn-ghost">{{ __('Cancel') }}</a>
    </div>
</x-sandbox-bank-layout>
