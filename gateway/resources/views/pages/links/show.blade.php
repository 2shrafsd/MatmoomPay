@php
    use App\Support\Money;
@endphp

<x-layouts::checkout :title="$link->title" :merchant="$merchant" :livemode="$link->livemode">
    <section class="surface mx-auto mt-6 max-w-lg p-5 sm:p-7" aria-labelledby="link-title">
        <p class="text-sm font-medium text-ink-700">{{ __('Payment request from :merchant', ['merchant' => $merchant->name]) }}</p>
        <h1 id="link-title" class="mt-1 display text-3xl break-words">{{ $link->title }}</h1>

        @if ($link->description)
            <p class="mt-2 whitespace-pre-line text-zinc-600">{{ $link->description }}</p>
        @endif

        @if ($link->hasFixedAmount())
            <p class="ltr-nums mt-5 text-start text-4xl font-medium tracking-tight text-ink-900">{{ Money::format($link->amount, $link->currency) }}</p>
        @endif

        @unless ($available)
            <div class="mt-6 rounded-xl bg-zinc-100 p-4 text-sm text-zinc-700" role="status">
                {{ __('This payment link is no longer accepting payments. Contact :merchant if you still need to pay.', ['merchant' => $merchant->name]) }}
            </div>
        @else
            <form method="POST" action="{{ route('links.start', $link) }}" class="mt-6 space-y-4" data-submit-once novalidate>
                @csrf

                @unless ($link->hasFixedAmount())
                    <div>
                        <label for="amount" class="mb-1.5 block text-sm font-medium">{{ __('Amount (:currency)', ['currency' => \App\Support\Money::currency()]) }}</label>
                        <input id="amount" name="amount" type="text" inputmode="decimal" dir="ltr" value="{{ old('amount') }}" placeholder="{{ \App\Support\Money::example() }}" required
                            class="field-input ltr-nums" @error('amount') aria-invalid="true" aria-describedby="amount-error" @enderror>
                        @if ($link->min_amount || $link->max_amount)
                            <p class="mt-1.5 text-xs text-zinc-500">
                                @if ($link->min_amount) {{ __('Minimum') }} <span class="ltr-nums">{{ Money::decimal($link->min_amount) }}</span> @endif
                                @if ($link->max_amount) · {{ __('Maximum') }} <span class="ltr-nums">{{ Money::decimal($link->max_amount) }}</span> @endif
                            </p>
                        @endif
                        @error('amount') <p id="amount-error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endunless

                <div>
                    <label for="name" class="mb-1.5 block text-sm font-medium">{{ __('Your name') }}</label>
                    <input id="name" name="name" type="text" autocomplete="name" maxlength="80" value="{{ old('name') }}" required
                        class="field-input" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                    @error('name') <p id="name-error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($link->collect_phone)
                    <div>
                        <label for="phone" class="mb-1.5 block text-sm font-medium">{{ __('Phone number') }}</label>
                        <input id="phone" name="phone" type="tel" autocomplete="tel" dir="ltr" maxlength="16" value="{{ old('phone') }}" placeholder="+249 91 234 5678" required
                            class="field-input ltr-nums" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                        @error('phone') <p id="phone-error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                @if ($link->collect_email)
                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-medium">{{ __('Email address') }}</label>
                        <input id="email" name="email" type="email" autocomplete="email" dir="ltr" maxlength="120" value="{{ old('email') }}" required
                            class="field-input" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        @error('email') <p id="email-error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <button type="submit" class="btn-primary w-full" data-busy-label="{{ __('Opening the payment page…') }}">
                    {{ __('Continue to payment') }}
                    <flux:icon.arrow-left variant="mini" class="ltr:rotate-180" />
                </button>
            </form>
        @endunless
    </section>
</x-layouts::checkout>
