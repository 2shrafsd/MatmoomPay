@php($cartCount = array_sum(session('cart', [])))

<header class="relative z-30 flex h-20 items-center justify-between gap-4 sm:h-24">
    <div class="flex items-center gap-10 xl:gap-16">
        <a href="{{ route('shop.index') }}" class="flex items-center gap-1.5 text-2xl font-bold tracking-tight">
            متجر<span class="mt-2 size-2 rounded-full bg-lime ring-1 ring-ink/10"></span>
        </a>
        <nav class="hidden items-center gap-8 text-sm font-medium text-ink/75 lg:flex">
            <a href="{{ route('shop.index', ['category' => 'headphones']) }}#products" class="hover:text-ink">سماعات الرأس</a>
            <a href="{{ route('shop.index') }}#features" class="hover:text-ink">المزايا</a>
            <a href="#support" class="hover:text-ink">الدعم</a>
            <a href="{{ route('shop.index') }}#products" class="hover:text-ink">المتجر</a>
        </nav>
    </div>

    <div class="flex items-center gap-1 sm:gap-2">
        <details class="relative">
            <summary class="grid size-10 place-items-center rounded-full hover:bg-white/70" aria-label="بحث">
                <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path stroke-linecap="round" d="m20 20-4.2-4.2"/></svg>
            </summary>
            <form method="GET" action="{{ route('shop.index') }}#products" class="absolute end-0 top-12 flex w-[min(18rem,calc(100vw-2rem))] items-center gap-2 rounded-full bg-white py-1.5 ps-4 pe-1.5 shadow-[0_8px_30px_rgb(0_0_0/0.08)] max-sm:-end-24">
                <label for="search" class="sr-only">ابحث عن منتج</label>
                <input id="search" name="q" value="{{ request('q') }}" placeholder="ابحث عن منتج" class="min-w-0 flex-1 bg-transparent text-sm placeholder:text-mute focus:outline-none">
                <button class="rounded-full bg-lime px-3 py-1.5 text-xs font-semibold">بحث</button>
            </form>
        </details>
        <a href="{{ route('cart.show') }}" class="relative grid size-10 place-items-center rounded-full hover:bg-white/70" aria-label="السلة، {{ $cartCount }} قطعة">
            <svg class="size-[19px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h2l2.2 10.6a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L19.8 8H6"/><circle cx="9" cy="19.5" r="1"/><circle cx="17" cy="19.5" r="1"/></svg>
            @if ($cartCount > 0)
                <span class="absolute end-0.5 top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-lime px-1 text-[10px] font-bold leading-none text-ink">{{ $cartCount }}</span>
            @endif
        </a>
        <a href="{{ route('shop.index') }}#products" class="ms-1 hidden rounded-full bg-lime px-5 py-2.5 text-sm font-semibold text-ink transition hover:brightness-95 active:scale-[0.98] sm:inline-block">تسوّق الآن</a>
        <details class="relative lg:hidden">
            <summary class="grid size-10 place-items-center rounded-full hover:bg-white/70" aria-label="القائمة">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M4 8h16M4 16h16"/></svg>
            </summary>
            <nav class="absolute end-0 top-12 flex w-48 flex-col gap-1 rounded-3xl bg-white p-3 text-sm font-medium shadow-[0_8px_30px_rgb(0_0_0/0.08)]">
                <a href="{{ route('shop.index', ['category' => 'headphones']) }}#products" class="rounded-2xl px-3 py-2 hover:bg-panel">سماعات الرأس</a>
                <a href="{{ route('shop.index') }}#features" class="rounded-2xl px-3 py-2 hover:bg-panel">المزايا</a>
                <a href="#support" class="rounded-2xl px-3 py-2 hover:bg-panel">الدعم</a>
                <a href="{{ route('shop.index') }}#products" class="rounded-2xl px-3 py-2 hover:bg-panel">المتجر</a>
                <a href="{{ route('orders.index') }}" class="rounded-2xl px-3 py-2 hover:bg-panel">طلباتي</a>
            </nav>
        </details>
    </div>
</header>
