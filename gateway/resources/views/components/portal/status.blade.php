@props(['status'])

@php
    $tone = match ($status->color()) {
        'green' => 'bg-mint-100 text-ink-800 ring-mint-300',
        'amber' => 'bg-gold-100 text-gold-700 ring-gold-300',
        'red' => 'bg-red-50 text-red-700 ring-red-200',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-200',
        default => 'bg-zinc-100 text-zinc-600 ring-zinc-200',
    };
@endphp

<span {{ $attributes->class("inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {$tone}") }}>
    <span class="size-1.5 rounded-full bg-current opacity-70"></span>
    {{ $status->label() }}
</span>
