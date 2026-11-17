{{--
    Gateway pagination, used by every <flux:table :paginate>. It replaces Flux's default so it sits inside the
    card with proper padding, reads correctly in Arabic (ranges stay left-to-right), has 40px touch targets,
    and brings the top of the table back into view after a page change.
--}}
<?php
extract(Flux::forwardedAttributes($attributes, ['scrollTo']));
?>

@props([
    'paginator' => null,
    'scrollTo' => $scrollTo ?? null,
])

@php
    $lengthAware = $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $cursor = method_exists($paginator, 'getCursorName');
    $pageName = $cursor ? $paginator->getCursorName() : $paginator->getPageName();

    // Bring the table's card back to the top of the screen when it scrolled out of view.
    $scrollBack = "const card = \$el.closest('.surface, [data-flux-table-card]'); if (card && card.getBoundingClientRect().top < 0) { card.scrollIntoView({ behavior: 'smooth', block: 'start' }) }";

    $button = 'inline-flex min-h-10 min-w-10 items-center justify-center gap-1.5 rounded-full px-3 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-mint-400/40';
    $idle = $button.' text-ink-900 hover:bg-mint-100';
    $disabled = $button.' cursor-not-allowed text-zinc-300';
    $current = $button.' bg-ink-800 text-white shadow-sm';

    if ($cursor) {
        $previousCall = "setPage('".($paginator->previousCursor() ?? $paginator->cursor())?->encode()."', '{$pageName}')";
        $nextCall = "setPage('".($paginator->nextCursor() ?? $paginator->cursor())?->encode()."', '{$pageName}')";
    } else {
        $previousCall = "previousPage('{$pageName}')";
        $nextCall = "nextPage('{$pageName}')";
    }

    $hasPages = $lengthAware ? $paginator->hasPages() : ($paginator->hasMorePages() || ! $paginator->onFirstPage());
@endphp

@if ($hasPages || ($lengthAware && $paginator->total() > 0))
    <nav {{ $attributes->class('@container flex flex-wrap items-center justify-between gap-x-4 gap-y-3 border-t border-zinc-200/80 px-4 py-3 sm:px-5') }} aria-label="{{ __('Page navigation') }}" data-flux-pagination>
        <p class="text-sm text-zinc-500" data-test="pagination-summary">
            @if ($lengthAware)
                {!! __('Showing :range of :total', [
                    'range' => '<bdi dir="ltr" class="font-medium text-ink-900">'.e($paginator->firstItem()).'–'.e($paginator->lastItem()).'</bdi>',
                    'total' => '<bdi dir="ltr" class="font-medium text-ink-900">'.e(number_format($paginator->total())).'</bdi>',
                ]) !!}
            @endif
        </p>

        @if ($hasPages)
            <div class="flex items-center gap-1">
                @if ($paginator->onFirstPage())
                    <span class="{{ $disabled }}" aria-disabled="true">
                        <flux:icon.chevron-right variant="micro" class="ltr:hidden" />
                        <flux:icon.chevron-left variant="micro" class="rtl:hidden" />
                        <span class="max-sm:sr-only">{{ __('Previous') }}</span>
                    </span>
                @else
                    <button type="button" wire:click="{{ $previousCall }}" x-on:click="{{ $scrollBack }}" class="{{ $idle }}" rel="prev" data-test="pagination-previous">
                        <flux:icon.chevron-right variant="micro" class="ltr:hidden" />
                        <flux:icon.chevron-left variant="micro" class="rtl:hidden" />
                        <span class="max-sm:sr-only">{{ __('Previous') }}</span>
                    </button>
                @endif

                @if ($lengthAware)
                    {{-- Page numbers on wide containers, "page x of y" on narrow ones --}}
                    <span class="px-2 text-sm text-zinc-500 @[36rem]:hidden">
                        {!! __('Page :current of :last', [
                            'current' => '<bdi class="font-medium text-ink-900">'.e($paginator->currentPage()).'</bdi>',
                            'last' => '<bdi class="font-medium text-ink-900">'.e($paginator->lastPage()).'</bdi>',
                        ]) !!}
                    </span>

                    <span class="hidden items-center gap-1 @[36rem]:flex">
                        @foreach (\Livewire\invade($paginator)->elements() as $element)
                            @if (is_string($element))
                                <span class="inline-flex min-w-8 justify-center text-sm text-zinc-400" aria-hidden="true">…</span>
                            @endif

                            @if (is_array($element))
                                @foreach ($element as $page => $url)
                                    @if ($page == $paginator->currentPage())
                                        <span wire:key="paginator-{{ $pageName }}-page{{ $page }}" class="{{ $current }} ltr-nums" aria-current="page">{{ $page }}</span>
                                    @else
                                        <button type="button" wire:key="paginator-{{ $pageName }}-page{{ $page }}" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')" x-on:click="{{ $scrollBack }}" class="{{ $idle }} ltr-nums" aria-label="{{ __('Page :number', ['number' => $page]) }}">{{ $page }}</button>
                                    @endif
                                @endforeach
                            @endif
                        @endforeach
                    </span>
                @endif

                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="{{ $nextCall }}" x-on:click="{{ $scrollBack }}" class="{{ $idle }}" rel="next" data-test="pagination-next">
                        <span class="max-sm:sr-only">{{ __('Next') }}</span>
                        <flux:icon.chevron-left variant="micro" class="ltr:hidden" />
                        <flux:icon.chevron-right variant="micro" class="rtl:hidden" />
                    </button>
                @else
                    <span class="{{ $disabled }}" aria-disabled="true">
                        <span class="max-sm:sr-only">{{ __('Next') }}</span>
                        <flux:icon.chevron-left variant="micro" class="ltr:hidden" />
                        <flux:icon.chevron-right variant="micro" class="rtl:hidden" />
                    </span>
                @endif
            </div>
        @endif
    </nav>
@endif
