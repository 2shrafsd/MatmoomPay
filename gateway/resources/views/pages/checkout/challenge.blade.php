@php
    use App\Enums\ChallengeType;
    use App\Support\Money;

    $isThreeDSecure = $payment->challenge_type === ChallengeType::ThreeDSecure;
@endphp

<x-layouts::checkout :title="__('Confirm your payment')" :merchant="$merchant" :livemode="$payment->livemode">
    <div class="mx-auto mt-6 max-w-md">
        <section class="surface overflow-hidden" aria-labelledby="verify-heading">
            @if ($isThreeDSecure)
                <div class="flex items-center justify-between gap-3 bg-ink-950 px-5 py-3 text-white">
                    <span class="flex items-center gap-2 text-sm font-semibold">
                        <flux:icon.shield-check variant="mini" />
                        {{ __('Your bank') }}
                    </span>
                    <span class="text-xs text-mint-200">{{ __('3-D Secure verification') }}</span>
                </div>
            @endif

            <div class="p-5 sm:p-7">
                <h1 id="verify-heading" class="display text-2xl">
                    {{ $isThreeDSecure ? __('Approve this payment') : __('Enter the verification code') }}
                </h1>
                <p class="mt-2 text-sm text-zinc-600">
                    {{ __('We sent a one-time code to the phone number registered with your bank.') }}
                </p>

                <dl class="mt-5 grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 rounded-xl bg-zinc-50 p-4 text-sm">
                    <dt class="text-zinc-500">{{ __('Merchant') }}</dt>
                    <dd class="font-medium">{{ $merchant->name }}</dd>
                    <dt class="text-zinc-500">{{ __('Amount') }}</dt>
                    <dd class="ltr-nums text-start font-medium">{{ Money::format($payment->amount, $payment->currency) }}</dd>
                    <dt class="text-zinc-500">{{ __('Card') }}</dt>
                    <dd class="ltr-nums text-start font-medium">{{ $payment->masked_card }}</dd>
                </dl>

                <form method="POST" action="{{ $action }}" class="mt-6 space-y-4" data-submit-once novalidate>
                    @csrf
                    <div>
                        <label for="code" class="mb-1.5 block text-sm font-medium">{{ __('One-time code') }}</label>
                        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" dir="ltr" maxlength="8" data-format="digits" autofocus required
                            class="field-input ltr-nums text-center text-2xl tracking-[0.5em]" @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
                        @error('code')
                            <p id="code-error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1.5 text-xs text-zinc-500">{{ trans_choice(':count attempt left|:count attempts left', $attemptsLeft, ['count' => $attemptsLeft]) }}</p>
                    </div>

                    <button type="submit" class="btn-primary w-full" data-busy-label="{{ __('Checking…') }}">{{ __('Confirm payment') }}</button>
                </form>

                @unless ($payment->livemode)
                    <p class="mt-4 rounded-lg bg-gold-100 px-3 py-2 text-xs text-zinc-800">{{ __('Test mode: the code is 1234.') }}</p>
                @endunless

                @if ($session)
                    <div class="mt-4 text-center">
                        <a href="{{ route('checkout.show', $session) }}" class="btn-ghost">{{ __('Use a different card') }}</a>
                    </div>
                @endif
            </div>
        </section>
    </div>
</x-layouts::checkout>
