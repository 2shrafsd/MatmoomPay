@php
    use App\Enums\CheckoutPaymentStatus;
    use App\Support\Money;

    [$icon, $tone, $heading, $body, $url, $cta] = match (true) {
        $session->payment_status === CheckoutPaymentStatus::Paid => ['check-circle', 'text-mint-600', __('This invoice is paid'), __('The payment for invoice #:invoice went through. You can close this page.', ['invoice' => $session->invoice]), $session->success_url, __('Back to :merchant', ['merchant' => $merchant->name])],
        $session->payment_status === CheckoutPaymentStatus::Cancelled => ['x-circle', 'text-zinc-500', __('This payment was cancelled'), __(':merchant cancelled this payment request.', ['merchant' => $merchant->name]), $session->cancel_url, __('Back to :merchant', ['merchant' => $merchant->name])],
        $session->isPayable() => ['no-symbol', 'text-zinc-500', __('This merchant cannot take payments right now'), __('Nothing was charged. Contact :merchant to complete your order.', ['merchant' => $merchant->name]), $session->cancel_url, __('Back to :merchant', ['merchant' => $merchant->name])],
        default => ['clock', 'text-gold-700', __('This payment link has expired'), __('Go back to :merchant and start the payment again.', ['merchant' => $merchant->name]), $session->cancel_url, __('Back to :merchant', ['merchant' => $merchant->name])],
    };
@endphp

<x-layouts::checkout :title="$heading" :merchant="$merchant" :livemode="$session->livemode">
    <section class="surface mx-auto mt-8 max-w-md p-6 text-center sm:p-8">
        <flux:icon :name="$icon" class="mx-auto size-12 {{ $tone }}" />
        <h1 class="display mt-4 text-2xl">{{ $heading }}</h1>
        <p class="mt-2 text-sm text-zinc-600">{{ $body }}</p>
        <p class="ltr-nums mt-4 text-lg font-semibold">{{ Money::format($session->total_amount, $session->currency) }}</p>

        @isset($payment)
            {{-- A receipt the payer can print or save as PDF; the browser's print dialog does the rest. --}}
            <div class="mt-6 rounded-2xl border border-zinc-200 p-4 text-start text-sm" data-test="receipt">
                <h2 class="font-medium text-ink-900">{{ __('Receipt') }}</h2>
                <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2">
                    <dt class="text-zinc-500">{{ __('Paid to') }}</dt><dd>{{ $merchant->name }}</dd>
                    <dt class="text-zinc-500">{{ __('Invoice') }}</dt><dd class="ltr-nums text-start">#{{ $session->invoice }}</dd>
                    <dt class="text-zinc-500">{{ __('Payment ID') }}</dt><dd class="ltr-nums text-start">{{ $payment->public_id }}</dd>
                    <dt class="text-zinc-500">{{ __('Date') }}</dt><dd>{{ $payment->completed_at?->locale(app()->getLocale())->translatedFormat('j F Y · H:i') }}</dd>
                    <dt class="text-zinc-500">{{ __('Card') }}</dt><dd class="ltr-nums text-start">{{ $payment->card_brand->value }} · {{ $payment->card_last_four }}</dd>
                </dl>
                <ul class="mt-4 divide-y divide-zinc-200 border-t border-zinc-200">
                    @foreach ($session->products as $product)
                        <li class="flex justify-between gap-3 py-2">
                            <span>{{ $product['name'] }} <span class="ltr-nums text-zinc-500">× {{ $product['quantity'] }}</span></span>
                            <span class="ltr-nums">{{ Money::format($product['quantity'] * $product['unit_amount'], $session->currency) }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 flex justify-between border-t border-zinc-200 pt-2 font-medium"><span>{{ __('Total') }}</span><span class="ltr-nums">{{ Money::format($session->total_amount, $session->currency) }}</span></p>
            </div>
            <button type="button" class="btn-secondary mt-4 w-full print:hidden" data-print>{{ __('Print or save the receipt') }}</button>
        @endisset

        <a href="{{ $url }}" class="btn-primary mt-6 w-full print:hidden">{{ $cta }}</a>
    </section>
</x-layouts::checkout>
