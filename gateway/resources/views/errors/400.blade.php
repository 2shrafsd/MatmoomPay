<x-error-page code="400" icon="exclamation-triangle" :title="__('Something in this request was not right')" :message="__('The page received information it could not use. Go back and try again; if you were paying, nothing was charged.')">
    <a href="{{ url()->previous() }}" class="btn-secondary">{{ __('Go back') }}</a>
</x-error-page>
