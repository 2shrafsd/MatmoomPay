@php
    use App\Support\Money;
@endphp

<x-layouts::checkout :title="__('Pay :merchant', ['merchant' => $session->merchant->name])" :merchant="$session->merchant" :livemode="$session->livemode">
    <div class="mt-4 grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,27rem)] lg:items-start lg:gap-8">
        <section aria-labelledby="order-summary" class="ink-card relative overflow-hidden p-6 sm:p-8">
            <div class="rings pointer-events-none absolute -end-48 -top-48 size-[32rem] opacity-[0.08] invert" aria-hidden="true"></div>
            <div class="relative">
                <h1 id="order-summary" class="text-sm text-white/70">{{ $session->isSubscription() ? __('Due today') : __('Amount due') }}</h1>
                <p class="ltr-nums mt-2 text-start text-4xl font-medium tracking-tight sm:text-5xl">{{ Money::format($session->total_amount, $session->currency) }}</p>
                <p class="mt-3 flex flex-wrap items-center gap-2 text-xs text-white/70">
                    <span class="rounded-full bg-white/10 px-3 py-1">{{ __('Invoice') }} <span class="ltr-nums">#{{ $session->invoice }}</span></span>
                    <span class="rounded-full bg-white/10 px-3 py-1">{{ __('Valid until :time', ['time' => $session->expire_at->locale(app()->getLocale())->translatedFormat('j M · H:i')]) }}</span>
                </p>

                <ul class="mt-7 space-y-3 border-t border-white/10 pt-5">
                    @foreach ($session->products as $product)
                        <li class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="break-words">{{ $product['name'] }}</p>
                                <p class="ltr-nums text-start text-xs text-white/60">{{ $product['quantity'] }} × {{ Money::decimal($product['unit_amount']) }}</p>
                            </div>
                            <p class="ltr-nums shrink-0">{{ Money::decimal($product['quantity'] * $product['unit_amount']) }}</p>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-5 flex items-center justify-between rounded-2xl bg-mint-400 px-4 py-3 font-medium text-ink-950">
                    <span>{{ $session->isSubscription() ? __('Due today') : __('Total') }}</span>
                    <span class="ltr-nums">{{ Money::format($session->total_amount, $session->currency) }}</span>
                </div>

                @if ($session->isSubscription() && $session->plan)
                    <p class="mt-3 flex items-center gap-2 text-sm text-white/80">
                        <flux:icon.arrow-path variant="micro" />
                        {{ __('Then :amount :interval until you cancel.', ['amount' => Money::format($session->plan->amount, $session->plan->currency), 'interval' => $session->plan->billingLabel()]) }}
                    </p>
                @endif
            </div>
        </section>

        <section aria-labelledby="pay-heading" class="surface p-5 sm:p-7">
            <h2 id="pay-heading" class="text-xl font-medium text-ink-900">{{ __('Pay by card') }}</h2>
            <p class="mt-1 text-sm text-zinc-500">{{ __('We accept Visa and Mastercard debit and credit cards.') }}</p>

            @error('payment')
                <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">
                    {{ $message }}
                </div>
            @enderror

            @if ($savedCards->isNotEmpty())
                <div class="mt-5 space-y-2">
                    <p class="text-sm font-medium">{{ __('Your saved cards') }}</p>
                    @foreach ($savedCards as $card)
                        <form method="POST" action="{{ route('checkout.pay-saved', [$session, $card]) }}" data-submit-once>
                            @csrf
                            <button type="submit" class="flex min-h-14 w-full items-center justify-between gap-3 rounded-2xl border border-zinc-200 px-4 text-start transition hover:border-ink-800 hover:bg-mint-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-mint-400/40" data-busy-label="{{ __('Processing…') }}">
                                <span class="flex items-center gap-3">
                                    <flux:icon.credit-card class="size-5 text-zinc-500" />
                                    <span>
                                        <span class="block font-medium">{{ $card->brand->value }} <span class="ltr-nums">•••• {{ $card->last_four }}</span></span>
                                        <span class="block text-xs text-zinc-500">{{ __('Expires') }} <span class="ltr-nums">{{ sprintf('%02d/%02d', $card->expiry_month, $card->expiry_year % 100) }}</span></span>
                                    </span>
                                </span>
                                <span class="text-sm font-semibold text-ink-700">{{ __('Pay') }}</span>
                            </button>
                        </form>
                    @endforeach
                </div>

                <div class="my-5 flex items-center gap-3 text-xs text-zinc-500" role="separator">
                    <span class="h-px flex-1 bg-zinc-200"></span>
                    {{ __('or use a new card') }}
                    <span class="h-px flex-1 bg-zinc-200"></span>
                </div>
            @endif

            @if ($hosted)
                <form method="POST" action="{{ route('checkout.hosted', $session) }}" class="mt-5 space-y-4" data-submit-once>
                    @csrf
                    <div class="flex items-start gap-3 rounded-xl border border-zinc-200 p-4 text-sm">
                        <flux:icon.building-library class="mt-0.5 size-5 shrink-0 text-ink-700" />
                        <p>{{ __('You will enter your card on your bank\'s secure page. Your card number never passes through this site.') }}</p>
                    </div>

                    @if ($session->isSubscription() && $session->plan)
                        <p class="rounded-xl bg-zinc-50 p-3 text-sm text-zinc-700" data-test="recurring-consent">
                            {{ __('By paying, you allow :merchant to save this card and charge :amount :interval until you cancel the subscription.', ['merchant' => $session->merchant->name, 'amount' => Money::format($session->plan->amount, $session->plan->currency), 'interval' => $session->plan->billingLabel()]) }}
                        </p>
                    @elseif ($canSaveCard)
                        <label class="flex items-start gap-3 rounded-xl bg-zinc-50 p-3 text-sm">
                            <input type="checkbox" name="save_card" value="1" @checked($session->save_card_on_success) class="mt-0.5 size-5 rounded border-zinc-300 accent-ink-800">
                            <span>{{ __('Save this card with :merchant for faster payments next time', ['merchant' => $session->merchant->name]) }}</span>
                        </label>
                    @endif

                    <button type="submit" class="btn-primary w-full" data-busy-label="{{ __('Opening your bank…') }}" data-test="pay-at-bank">
                        <flux:icon.lock-closed variant="mini" />
                        {{ __('Pay') }} <span class="ltr-nums">{{ Money::format($session->total_amount, $session->currency) }}</span>
                    </button>
                </form>
            @else
            <form method="POST" action="{{ route('checkout.pay', $session) }}" class="mt-5 space-y-4" data-submit-once novalidate>
                    @csrf
    
                    <div>
                        <label for="card_number" class="mb-1.5 block text-sm font-medium">{{ __('Card number') }}</label>
                        <input id="card_number" name="card_number" type="text" inputmode="numeric" autocomplete="cc-number" dir="ltr" maxlength="23" placeholder="1234 1234 1234 1234" data-format="card" required
                            class="field-input ltr-nums tracking-wider" @error('card_number') aria-invalid="true" aria-describedby="card_number-error" @enderror>
                        @error('card_number') <p id="card_number-error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="expiry" class="mb-1.5 block text-sm font-medium">{{ __('Expiry date') }}</label>
                            <input id="expiry" name="expiry" type="text" inputmode="numeric" autocomplete="cc-exp" dir="ltr" maxlength="5" placeholder="MM/YY" data-format="expiry" required
                                class="field-input ltr-nums" @error('expiry') aria-invalid="true" aria-describedby="expiry-error" @enderror>
                            @error('expiry') <p id="expiry-error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="cvc" class="mb-1.5 block text-sm font-medium">{{ __('Security code') }}</label>
                            <input id="cvc" name="cvc" type="password" inputmode="numeric" autocomplete="cc-csc" dir="ltr" maxlength="4" placeholder="CVV" data-format="digits" required
                                class="field-input ltr-nums" @error('cvc') aria-invalid="true" aria-describedby="cvc-error" @enderror>
                            @error('cvc') <p id="cvc-error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
    
                    <div>
                        <label for="cardholder_name" class="mb-1.5 block text-sm font-medium">{{ __('Name on card') }}</label>
                        <input id="cardholder_name" name="cardholder_name" type="text" autocomplete="cc-name" dir="ltr" maxlength="60" value="{{ old('cardholder_name') }}" required
                            class="field-input" @error('cardholder_name') aria-invalid="true" aria-describedby="cardholder_name-error" @enderror>
                        @error('cardholder_name') <p id="cardholder_name-error" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
    
                    @if ($session->isSubscription() && $session->plan)
                        <p class="rounded-xl bg-zinc-50 p-3 text-sm text-zinc-700" data-test="recurring-consent">
                            {{ __('By paying, you allow :merchant to save this card and charge :amount :interval until you cancel the subscription.', ['merchant' => $session->merchant->name, 'amount' => Money::format($session->plan->amount, $session->plan->currency), 'interval' => $session->plan->billingLabel()]) }}
                        </p>
                    @elseif ($canSaveCard)
                        <label class="flex items-start gap-3 rounded-xl bg-zinc-50 p-3 text-sm">
                            <input type="checkbox" name="save_card" value="1" @checked($session->save_card_on_success) class="mt-0.5 size-5 rounded border-zinc-300 accent-ink-800">
                            <span>{{ __('Save this card with :merchant for faster payments next time', ['merchant' => $session->merchant->name]) }}</span>
                        </label>
                    @endif
    
                    <button type="submit" class="btn-primary w-full" data-busy-label="{{ __('Processing…') }}">
                        <flux:icon.lock-closed variant="mini" />
                        {{ __('Pay') }} <span class="ltr-nums">{{ Money::format($session->total_amount, $session->currency) }}</span>
                    </button>
    
                    <p class="text-center text-xs text-zinc-500">
                        {{ __('Your bank may ask you to confirm the payment with a one-time code.') }}
                    </p>
                </form>
            @endif

            <div class="mt-4 text-center">
                <a href="{{ route('checkout.cancel', $session) }}" class="btn-ghost">
                    {{ __('Cancel and return to :merchant', ['merchant' => $session->merchant->name]) }}
                </a>
            </div>
        </section>
    </div>

    @unless ($session->livemode)
        <details class="surface mt-5 p-5 text-sm sm:p-6">
            <summary class="cursor-pointer font-semibold">{{ __('Test cards') }}</summary>
            <p class="mt-2 text-zinc-600">{{ __('Use any future expiry date and any security code. The verification code is always 1234.') }}</p>
            <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                @foreach ([
                    '4242 4242 4242 4242' => __('Approved after OTP'),
                    '4000 0000 0000 0002' => __('Declined after OTP'),
                    '4456 5300 0000 1096' => __('3-D Secure, approved'),
                    '4456 5300 0000 1104' => __('3-D Secure, declined'),
                ] as $number => $outcome)
                    <div class="rounded-lg bg-zinc-50 px-3 py-2">
                        <dt class="ltr-nums font-mono text-start">{{ $number }}</dt>
                        <dd class="text-zinc-500">{{ $outcome }}</dd>
                    </div>
                @endforeach
            </dl>
        </details>
    @endunless
</x-layouts::checkout>
