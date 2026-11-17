@props([
    'status',
])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-2xl bg-mint-100 px-4 py-3 text-sm font-medium text-ink-900']) }} role="status">
        {{ $status }}
    </div>
@endif
