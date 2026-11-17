<x-error-page code="403" icon="no-symbol" :title="__('You do not have access to this page')" :message="__('Your account is not allowed to do this. If you think it should be, ask the account owner to change your role.')">
    <a href="{{ url()->previous() }}" class="btn-secondary">{{ __('Go back') }}</a>
</x-error-page>
