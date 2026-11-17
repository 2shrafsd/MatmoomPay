<x-error-page code="401" icon="lock-closed" :title="__('Sign in to continue')" :message="__('This page is only for signed-in users. Sign in, then try again.')">
    <a href="{{ route('login') }}" class="btn-secondary">{{ __('Log in') }}</a>
</x-error-page>
