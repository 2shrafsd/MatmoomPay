@props(['heading', 'subheading' => null, 'eyebrow' => null])

<div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="eyebrow mb-2">{{ $eyebrow }}</p>
        @endif
        <h1 class="display text-3xl sm:text-4xl">{{ $heading }}</h1>
        @if ($subheading)
            <p class="mt-2 max-w-2xl text-zinc-600">{{ $subheading }}</p>
        @endif
    </div>
    @if (isset($actions))
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endif
</div>
