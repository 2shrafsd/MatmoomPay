<x-layouts::checkout :title="__('Payment confirmed')" :livemode="true">
    <section class="surface mx-auto mt-8 max-w-md p-6 text-center sm:p-8">
        <flux:icon.check-circle class="mx-auto size-14 text-mint-600" />
        <h1 class="mt-4 display text-3xl">{{ __('Thank you, your bank confirmed the payment') }}</h1>
        <p class="mt-2 text-zinc-600">{{ __('Your subscription continues as normal. You can close this page.') }}</p>
    </section>
</x-layouts::checkout>
