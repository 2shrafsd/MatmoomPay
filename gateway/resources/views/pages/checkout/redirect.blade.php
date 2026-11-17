<x-layouts::checkout :title="__('Opening your bank')" :livemode="true">
    <section class="surface mx-auto mt-8 max-w-md p-6 text-center">
        <p class="text-zinc-600">{{ __('Taking you to your bank to complete the payment…') }}</p>
        <form id="bank-redirect" method="POST" action="{{ $redirect->url }}" class="mt-6">
            @foreach ($redirect->fields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <button type="submit" class="btn-primary w-full">{{ __('Continue to your bank') }}</button>
        </form>
    </section>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">document.getElementById('bank-redirect').submit();</script>
</x-layouts::checkout>
