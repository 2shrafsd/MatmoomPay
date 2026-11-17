{{-- Shared pages (profile, security, password confirmation) open inside the dashboard the person is working in. --}}
@if (\App\Support\Panel::isAdmin())
    <x-layouts::admin :title="$title ?? null">{{ $slot }}</x-layouts::admin>
@else
    <x-layouts::app :title="$title ?? null">{{ $slot }}</x-layouts::app>
@endif
