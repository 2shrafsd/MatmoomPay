@props(['class' => ''])

@php($next = app()->getLocale() === 'ar' ? 'en' : 'ar')

<form method="POST" action="{{ route('locale.update') }}" class="{{ $class }}">
    @csrf
    <input type="hidden" name="locale" value="{{ $next }}">
    <button type="submit" class="inline-flex min-h-9 items-center gap-1.5 rounded-full px-3 text-xs font-medium text-ink-900 ring-1 ring-zinc-200 transition hover:bg-mint-100" lang="{{ $next }}">
        <flux:icon.language variant="micro" class="text-ink-600" />
        {{ $next === 'ar' ? 'العربية' : 'English' }}
    </button>
</form>
