@props(['icon' => 'inbox', 'heading', 'body' => null])

<div {{ $attributes->class('flex flex-col items-center justify-center gap-3 px-6 py-16 text-center') }}>
    <span class="grid size-14 place-items-center rounded-full bg-mint-100 text-ink-800 ring-8 ring-mint-50">
        <flux:icon :name="$icon" />
    </span>
    <p class="mt-2 text-lg font-medium text-ink-900">{{ $heading }}</p>
    @if ($body)
        <p class="max-w-sm text-sm text-zinc-500">{{ $body }}</p>
    @endif
    {{ $slot }}
</div>
