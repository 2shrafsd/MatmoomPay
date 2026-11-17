<x-error-page code="410" icon="clock" :title="__('This page is no longer available')" :message="__('The payment step it belonged to has ended. Go back to the store and start the payment again.')">
    <a href="{{ url()->previous() }}" class="btn-secondary">{{ __('Go back') }}</a>
</x-error-page>
