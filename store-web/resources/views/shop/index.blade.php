@extends('shop.layout', ['title' => 'سماعات ومعدات صوت'])

@php
    $filters = array_filter(['category' => $category, 'sort' => $sort, 'max' => $maxPrice, 'q' => $search]);
    $shopUrl = fn (array $changes) => route('shop.index', array_filter([...$filters, ...$changes])).'#products';
    $heading = match (true) {
        $search !== '' => 'نتائج «'.$search.'»',
        $category !== '' => $tabs[$category],
        default => 'كل المنتجات',
    };
    $arrow = '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m6-6-6 6 6 6"/></svg>';
    $chevron = '<svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>';
@endphp

@section('hero')
    <section class="grid lg:min-h-[640px] lg:grid-cols-2">
        <div class="flex flex-col justify-center px-5 pt-6 pb-10 sm:px-10 lg:px-14 lg:pb-14">
            <p class="eyebrow">سماعات لاسلكية</p>
            <h1 class="mt-5 text-[2.75rem] leading-[1.08] font-bold tracking-tight sm:text-6xl xl:text-7xl">
                صوت أنقى،<br>تركيز أطول،<br>يوم عمل كامل.
            </h1>
            <p class="mt-6 max-w-md text-base leading-relaxed text-mute sm:text-lg">
                عزل للضوضاء، مكالمات واضحة، وبطارية تكفي أسبوع عمل. تتصل بحاسوبك وهاتفك في ثوانٍ.
            </p>
            <div class="mt-8 flex flex-wrap items-center gap-x-7 gap-y-4">
                <a href="{{ route('shop.index', ['category' => 'headphones']) }}#products" class="inline-flex items-center gap-2.5 rounded-full bg-lime px-6 py-3.5 text-sm font-semibold text-ink transition hover:brightness-95 active:scale-[0.98]">
                    تصفّح السماعات {!! $arrow !!}
                </a>
                <a href="#features" class="inline-flex items-center gap-2.5 text-sm font-medium hover:underline hover:underline-offset-4">
                    شاهد المزايا
                    <span class="grid size-6 place-items-center rounded-full border border-ink/60">{!! str_replace('size-4', 'size-3', $arrow) !!}</span>
                </a>
            </div>

            <div class="mt-12 lg:mt-16">
                <p class="text-xs text-mute">تعمل مع</p>
                <ul class="mt-3 flex flex-wrap gap-x-7 gap-y-3 text-sm text-ink/80" dir="ltr">
                    <li class="flex items-center gap-2">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="4" y="5" width="16" height="11" rx="1.5"/><path stroke-linecap="round" d="M2 19h20"/></svg>MacBook
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="1.5"/><path d="M12 4v16M4 12h16"/></svg>Windows
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path stroke-linecap="round" d="M11 18h2"/></svg>iPad
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path stroke-linecap="round" d="M11 5h2"/></svg>iPhone
                    </li>
                </ul>
            </div>
        </div>

        <div class="relative mx-3 mb-3 aspect-[4/3] overflow-hidden rounded-[22px] sm:mx-5 sm:mb-5 lg:m-0 lg:aspect-auto lg:rounded-none">
            <img src="{{ asset('images/site/hero-headphones.jpg') }}" alt="سماعة رأس لاسلكية بلون بيج على قماش أبيض" width="1600" height="947"
                 class="absolute inset-0 size-full object-cover object-[40%_50%]">
            <div class="pointer-events-none absolute inset-y-0 start-0 hidden w-24 bg-gradient-to-l from-panel to-transparent lg:block"></div>
        </div>
    </section>
@endsection

@section('content')
    {{-- Features --}}
    <section id="features" class="scroll-mt-6 pt-20 sm:pt-28">
        <div class="grid gap-6 px-2 lg:grid-cols-[1.4fr_1fr] lg:items-end lg:px-6">
            <div>
                <p class="eyebrow">المزايا</p>
                <h2 class="mt-4 text-3xl font-bold tracking-tight sm:text-5xl">مصممة لطريقة عملك.</h2>
            </div>
            <p class="max-w-md text-base leading-relaxed text-mute">
                سماعات لاسلكية مريحة طوال اليوم، بمكالمات واضحة وأداء ثابت مع الحاسوب والهاتف.
            </p>
        </div>

        @php
            $features = [
                ['icon' => 'M13 2 4 14h7l-1 8 9-12h-7l1-8Z', 'title' => 'حتى 60 ساعة<br>من البطارية', 'text' => 'تكفي للعمل والاجتماعات والترفيه طوال الأسبوع.', 'photo' => 'feature-battery.jpg', 'big' => '60', 'unit' => 'ساعة تشغيل'],
                ['icon' => 'M12 3a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3Zm-7 9a7 7 0 0 0 14 0M12 19v3', 'title' => 'مكالمات واضحة<br>أينما كنت', 'text' => 'ميكروفونات تلتقط صوتك وتخفّف ضجيج المكان.', 'photo' => 'feature-calls.jpg'],
                ['icon' => 'M5 19c0-8 5-13 14-14-1 9-6 14-14 14Zm0 0 7-7', 'title' => 'راحة<br>طوال اليوم', 'text' => 'وزن خفيف ووسائد طرية لجلسات العمل الطويلة.', 'photo' => 'feature-comfort.jpg'],
            ];
        @endphp
        <div class="mt-10 grid gap-4 md:grid-cols-3">
            @foreach ($features as $feature)
                <article class="relative min-h-[400px] overflow-hidden rounded-[24px] bg-panel sm:min-h-[440px]">
                    <div class="relative z-10 max-w-[20rem] p-6 sm:p-7">
                        <div class="flex items-center gap-3">
                            <span class="grid size-11 shrink-0 place-items-center rounded-full bg-lime">
                                <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $feature['icon'] }}"/></svg>
                            </span>
                            <h3 class="text-lg leading-snug font-bold">{!! $feature['title'] !!}</h3>
                        </div>
                        <p class="mt-4 max-w-[15rem] text-sm leading-relaxed text-mute">{{ $feature['text'] }}</p>
                    </div>
                    @isset($feature['big'])
                        <p class="absolute start-7 bottom-6 z-10 leading-none text-ink/25">
                            <span class="block text-7xl font-bold tracking-tight">{{ $feature['big'] }}</span>
                            <span class="mt-2 block text-base font-medium">{{ $feature['unit'] }}</span>
                        </p>
                    @endisset
                    <img src="{{ asset('images/site/'.$feature['photo']) }}" alt="" width="800" height="800" loading="lazy"
                         class="absolute end-0 bottom-0 h-[60%] w-[62%] rounded-ss-[24px] object-cover mix-blend-multiply">
                </article>
            @endforeach
        </div>
    </section>

    {{-- Products --}}
    <section id="products" class="scroll-mt-6 pt-20 sm:pt-28">
        <div class="flex flex-col gap-6 px-2 lg:flex-row lg:items-end lg:justify-between lg:px-6">
            <div>
                <p class="eyebrow">المتجر</p>
                <h2 class="mt-4 text-3xl font-bold tracking-tight sm:text-5xl">{{ $heading }}</h2>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <details class="relative">
                    <summary class="flex items-center gap-1.5 rounded-full border border-line bg-white px-4 py-2 font-medium hover:border-ink/30">التصفية {!! $chevron !!}</summary>
                    <ul class="absolute start-0 top-12 z-20 w-52 space-y-1 rounded-3xl bg-white p-3 shadow-[0_8px_30px_rgb(0_0_0/0.08)] lg:start-auto lg:end-0">
                        <li><a href="{{ $shopUrl(['max' => null]) }}" @class(['block rounded-2xl px-3 py-2 hover:bg-panel', 'bg-panel font-semibold' => ! $maxPrice])>كل الأسعار</a></li>
                        @foreach (\App\Http\Controllers\ShopController::PRICE_LIMITS as $limit)
                            <li><a href="{{ $shopUrl(['max' => $limit]) }}" @class(['block rounded-2xl px-3 py-2 hover:bg-panel', 'bg-panel font-semibold' => $maxPrice === $limit])>حتى {{ \App\Support\Sdg::format($limit) }}</a></li>
                        @endforeach
                    </ul>
                </details>
                <details class="relative">
                    <summary class="flex items-center gap-1.5 rounded-full border border-line bg-white px-4 py-2 font-medium hover:border-ink/30">الترتيب {!! $chevron !!}</summary>
                    <ul class="absolute end-0 top-12 z-20 w-48 space-y-1 rounded-3xl bg-white p-3 shadow-[0_8px_30px_rgb(0_0_0/0.08)]">
                        <li><a href="{{ $shopUrl(['sort' => null]) }}" @class(['block rounded-2xl px-3 py-2 hover:bg-panel', 'bg-panel font-semibold' => $sort === ''])>المقترح</a></li>
                        @foreach (\App\Http\Controllers\ShopController::SORTS as $key => $label)
                            <li><a href="{{ $shopUrl(['sort' => $key]) }}" @class(['block rounded-2xl px-3 py-2 hover:bg-panel', 'bg-panel font-semibold' => $sort === $key])>{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </details>
            </div>
        </div>

        <nav class="-mx-3 mt-8 overflow-x-auto px-3 sm:mx-0 sm:px-2 lg:px-6" aria-label="الأقسام">
            <ul class="flex w-max gap-2 text-sm">
                @foreach ($tabs as $key => $label)
                    <li>
                        <a href="{{ $shopUrl(['category' => $key, 'q' => null]) }}" @class([
                            'block whitespace-nowrap rounded-full px-4 py-2 font-medium transition',
                            'bg-lime text-ink' => $category === $key,
                            'bg-panel text-ink/70 hover:text-ink' => $category !== $key,
                        ]) @if ($category === $key) aria-current="page" @endif>{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        @if ($products->isEmpty())
            <div class="mt-8 rounded-[24px] bg-panel py-20 text-center text-sm text-mute">
                لا توجد منتجات تطابق اختيارك.
                <a href="{{ route('shop.index') }}#products" class="font-medium text-ink underline underline-offset-4">عرض كل المنتجات</a>
            </div>
        @else
            <div class="mt-8 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4 2xl:gap-5">
                @foreach ($products as $product)
                    <article class="group flex flex-col rounded-[24px] bg-panel p-2.5 sm:p-3" title="{{ $product->description }}">
                        <div class="relative overflow-hidden rounded-[18px] bg-[#ebe8e3]">
                            <x-product-photo :product="$product" class="mix-blend-multiply transition duration-500 group-hover:scale-[1.03]" />
                            <form method="POST" action="{{ route('cart.add', $product) }}" class="absolute end-2.5 top-2.5">
                                @csrf
                                <button class="grid size-9 place-items-center rounded-full bg-lime text-ink shadow-[0_2px_8px_rgb(0_0_0/0.12)] transition hover:brightness-95 active:scale-90 sm:size-10" aria-label="أضف للسلة: {{ $product->name }}" title="أضف للسلة">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                                </button>
                            </form>
                        </div>
                        <div class="flex flex-1 flex-col px-1.5 pt-3 pb-1.5 sm:px-2">
                            <p class="text-[11px] text-mute">{{ \App\Models\Product::CATEGORIES[$product->category] ?? '' }}</p>
                            <h3 class="mt-0.5 flex-1 text-sm leading-snug font-semibold sm:text-[15px]">{{ $product->name }}</h3>
                            <p class="mt-2 text-sm text-mute">{{ \App\Support\Sdg::format($product->price) }}</p>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($moreUrl)
                <p class="mt-10 text-center">
                    <a href="{{ $moreUrl }}#products" class="inline-block rounded-full border border-ink/80 px-6 py-2.5 text-sm font-semibold transition hover:bg-ink hover:text-white">عرض المزيد</a>
                </p>
            @endif
        @endif
    </section>

    {{-- About --}}
    <section class="mt-20 rounded-[28px] bg-panel p-3 sm:mt-28 sm:p-5">
        <div class="grid items-center gap-8 lg:grid-cols-[1.2fr_1fr] lg:gap-14">
            <img src="{{ asset('images/site/about-desk.jpg') }}" alt="سماعة رأس بجانب لوحة مفاتيح وشاشة على مكتب أبيض" width="1440" height="900" loading="lazy"
                 class="aspect-[16/10] w-full rounded-[20px] object-cover">
            <div class="px-3 pb-4 sm:px-4 lg:px-0 lg:pe-10">
                <p class="eyebrow">عن متجر</p>
                <h2 class="mt-4 text-3xl leading-tight font-bold tracking-tight sm:text-4xl">صوت أنقى<br>ليوم عمل أهدأ.</h2>
                <p class="mt-5 max-w-lg leading-relaxed text-mute">
                    متجر صغير لسماعات ومعدات صوت لمن يعمل من حاسوبه: طلاب ومصممون وموظفون. الأسعار بالجنيه السوداني، والدفع بالبطاقة، والتوصيل إلى باب البيت.
                </p>
            </div>
        </div>

        @php
            $promises = [
                ['تركيز', 'أعلى', 'M4 15v-3a8 8 0 0 1 16 0v3M4 15a2 2 0 0 1 2-2h1v6H6a2 2 0 0 1-2-2v-2Zm16 0a2 2 0 0 0-2-2h-1v6h1a2 2 0 0 0 2-2v-2Z'],
                ['مكالمات', 'واضحة', 'M4 10v4M8 7v10M12 4v16M16 7v10M20 10v4'],
                ['تنقّل بين', 'الأجهزة', 'M7 3h10a1.5 1.5 0 0 1 1.5 1.5v15A1.5 1.5 0 0 1 17 21H7a1.5 1.5 0 0 1-1.5-1.5v-15A1.5 1.5 0 0 1 7 3Zm4 15h2'],
                ['أداء', 'يُعتمد عليه', 'M12 3 5 6v5c0 4.5 3 8.3 7 10 4-1.7 7-5.5 7-10V6l-7-3Zm-3 9 2 2 4-4'],
            ];
        @endphp
        <ul class="mt-6 grid grid-cols-2 gap-y-6 rounded-[20px] py-5 lg:grid-cols-4 lg:divide-x lg:divide-line">
            @foreach ($promises as [$first, $second, $icon])
                <li class="flex items-center gap-3 px-3 sm:px-6">
                    <span class="grid size-12 shrink-0 place-items-center rounded-full bg-white">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    </span>
                    <span class="text-sm leading-snug font-medium">{{ $first }}<br>{{ $second }}</span>
                </li>
            @endforeach
        </ul>
    </section>
@endsection
