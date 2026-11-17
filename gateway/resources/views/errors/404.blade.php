<x-error-page code="404" icon="magnifying-glass" :title="__('We could not find this page')" :message="__('The link may be old or mistyped. Payment links stop working once the merchant closes them.')">
    <a href="{{ url()->previous() }}" class="btn-secondary">{{ __('Go back') }}</a>
</x-error-page>
