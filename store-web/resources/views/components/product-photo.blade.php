@props(['product', 'size' => 'card'])

{{-- The product photo, or its colour with the first letter when the photo is missing. --}}
@if ($product?->imageUrl())
    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" loading="lazy" width="900" height="900"
         {{ $attributes->merge(['class' => 'aspect-square w-full object-cover']) }}>
@else
    <div {{ $attributes->merge(['class' => 'grid aspect-square w-full place-items-center font-semibold text-white']) }}
         style="background: {{ $product?->color ?? '#a8a29e' }}" role="img" aria-label="{{ $product?->name }}">
        <span class="{{ $size === 'card' ? 'text-5xl' : 'text-lg' }}">{{ mb_substr($product?->name ?? '؟', 0, 1) }}</span>
    </div>
@endif
