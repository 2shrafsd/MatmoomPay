<x-sandbox-bank-layout :order="$order" :title="__('Enter the verification code')">
    <p class="mt-2 text-sm text-slate-600">{{ __('We sent a one-time code to the phone number registered with your bank.') }} <span class="ltr-nums">{{ $card->brand->value }} •••• {{ $card->lastFour }}</span></p>
    <form method="POST" action="{{ route('sandbox-bank.otp') }}" class="mt-4 space-y-4" data-submit-once novalidate>
        @csrf
        <input type="hidden" name="p" value="{{ $p }}">
        <input type="hidden" name="s" value="{{ $s }}">
        <div>
            <label for="code" class="mb-1.5 block text-sm font-medium">{{ __('One-time code') }}</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" dir="ltr" maxlength="8" data-format="digits" autofocus required class="field-input ltr-nums text-center text-2xl tracking-[0.5em]" @error('code') aria-invalid="true" @enderror>
            @error('code') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn-primary w-full" data-busy-label="{{ __('Checking…') }}">{{ __('Confirm payment') }}</button>
    </form>
    <p class="mt-4 rounded-lg bg-gold-100 px-3 py-2 text-xs">{{ __('Test mode: the code is 1234.') }}</p>
</x-sandbox-bank-layout>
