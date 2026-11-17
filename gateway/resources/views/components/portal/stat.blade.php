@props(['label', 'value', 'hint' => null, 'icon' => null])

<div {{ $attributes->class('surface flex flex-col gap-3 p-5') }}>
    <div class="flex items-center justify-between gap-2">
        <span class="text-sm text-zinc-500">{{ $label }}</span>
        @if ($icon)
            <span class="grid size-9 place-items-center rounded-full bg-mint-100 text-ink-800"><flux:icon :name="$icon" variant="mini" /></span>
        @endif
    </div>
    <p class="ltr-nums text-start text-2xl font-medium tracking-tight text-ink-900 sm:text-[1.7rem]">{{ $value }}</p>
    @if ($hint)
        <p class="text-xs text-zinc-500">{{ $hint }}</p>
    @endif
</div>
