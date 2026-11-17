@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-start">
    <h1 class="display text-3xl sm:text-4xl">{{ $title }}</h1>
    <p class="mt-3 text-zinc-600">{{ $description }}</p>
</div>
