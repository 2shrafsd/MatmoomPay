@props(['value', 'label' => null])

<div {{ $attributes->class('flex min-w-0 items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50 ps-4 pe-1 py-1') }} x-data="{ copied: false }">
    <code class="ltr-nums min-w-0 flex-1 truncate text-start font-mono text-sm text-ink-900" dir="ltr">{{ $value }}</code>
    <button type="button" class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-ink-900 ring-1 ring-zinc-200 hover:bg-mint-100" x-on:click="navigator.clipboard.writeText(@js($value)); copied = true; setTimeout(() => copied = false, 1500)" aria-label="{{ $label ?? __('Copy') }}">
        <flux:icon.clipboard-document variant="micro" />
        <span x-show="!copied">{{ __('Copy') }}</span>
        <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
    </button>
</div>
