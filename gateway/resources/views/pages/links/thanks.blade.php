@php
    use App\Enums\CheckoutPaymentStatus;
    use App\Support\Money;

    $paid = $session?->payment_status === CheckoutPaymentStatus::Paid;
@endphp

<x-layouts::checkout :title="$paid ? __('Payment received') : __('Payment status')" :merchant="$merchant" :livemode="$link->livemode">
    <section class="surface mx-auto mt-8 max-w-md p-6 text-center sm:p-8">
        @if ($paid)
            <flux:icon.check-circle class="mx-auto size-14 text-mint-600" />
            <h1 class="mt-4 display text-3xl">{{ __('Payment received') }}</h1>
            <p class="mt-2 text-zinc-600">{{ __(':merchant has received your payment for “:title”.', ['merchant' => $merchant->name, 'title' => "\u{2068}".$link->title."\u{2069}"]) }}</p>
            <dl class="mt-6 grid grid-cols-2 gap-3 text-start text-sm">
                <div class="rounded-xl bg-zinc-50 p-3">
                    <dt class="text-zinc-500">{{ __('Amount') }}</dt>
                    <dd class="ltr-nums mt-0.5 text-start font-semibold">{{ Money::format($session->total_amount, $session->currency) }}</dd>
                </div>
                <div class="rounded-xl bg-zinc-50 p-3">
                    <dt class="text-zinc-500">{{ __('Invoice') }}</dt>
                    <dd class="ltr-nums mt-0.5 text-start font-semibold">#{{ $session->invoice }}</dd>
                </div>
            </dl>
            <p class="mt-4 text-xs text-zinc-500">{{ __('Keep the invoice number in case you need to contact the merchant.') }}</p>
        @else
            <flux:icon.clock class="mx-auto size-14 text-gold-700" />
            <h1 class="mt-4 display text-3xl">{{ __('We have not received this payment') }}</h1>
            <p class="mt-2 text-zinc-600">{{ __('If you completed the payment, it can take a moment to appear. Otherwise you can try again.') }}</p>
            <a href="{{ route('links.show', $link) }}" class="btn-primary mt-6 w-full">{{ __('Try again') }}</a>
        @endif
    </section>
</x-layouts::checkout>
