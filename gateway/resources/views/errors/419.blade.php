<x-error-page code="419" icon="clock" :title="__('This page has expired')" :message="__('For your security, forms expire after a while. Go back, refresh the page and try again.')">
    <a href="{{ url()->previous() }}" class="btn-secondary">{{ __('Go back') }}</a>
</x-error-page>
