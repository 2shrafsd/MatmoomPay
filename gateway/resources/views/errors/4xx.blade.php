<x-error-page :code="$exception->getStatusCode()" icon="exclamation-circle" :title="__('We could not complete this request')" :message="__('Go back and try again. If it keeps happening, contact the store you were paying.')">
    <a href="{{ url()->previous() }}" class="btn-secondary">{{ __('Go back') }}</a>
</x-error-page>
