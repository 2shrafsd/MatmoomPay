<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'متجر' }} · متجر</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.1.11"></script>
    <style type="text/tailwindcss">
        @theme {
            --font-sans: "IBM Plex Sans Arabic", system-ui, Tahoma, sans-serif;
            --color-page: #fafaf9;
            --color-panel: #f2f1ef;
            --color-ink: #141414;
            --color-mute: #6b6b6b;
            --color-line: #e3e1dd;
            --color-lime: #e3fa5a;
        }
        @layer base {
            html { scroll-behavior: smooth; }
            summary { list-style: none; cursor: pointer; }
            summary::-webkit-details-marker { display: none; }
        }
        @utility eyebrow {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--color-mute);
            &::after { content: ""; width: 2.25rem; height: 1px; background: currentColor; opacity: 0.6; }
        }
    </style>
</head>
<body class="bg-page font-sans text-ink antialiased">
    <div class="bg-lime">
        @include('shop.partials.test-notice', ['class' => 'mx-auto max-w-[1600px] px-4 py-3 text-[15px] sm:text-base'])
    </div>

    <div class="mx-auto w-full max-w-[1600px] px-3 pt-3 sm:px-5 sm:pt-5 lg:px-8">

        <div class="relative overflow-hidden rounded-[28px] bg-panel">
            <div class="px-5 sm:px-10 lg:px-14">
                @include('shop.partials.header')
            </div>
            @yield('hero')
        </div>

        <main class="pb-16">
            @if ($errors->any())
                <div class="mt-6 rounded-3xl bg-panel px-6 py-4 text-sm text-red-700" role="alert">
                    @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif
            @yield('content')
        </main>

        <footer id="support" class="border-t border-line pt-14 pb-8">
            <div class="grid grid-cols-2 gap-x-8 gap-y-10 text-sm sm:grid-cols-4 lg:grid-cols-[2fr_1fr_1fr_1fr]">
                <div class="col-span-2 sm:col-span-4 lg:col-span-1">
                    <a href="{{ route('shop.index') }}" class="flex items-center gap-1.5 text-xl font-bold">متجر<span class="mt-1.5 size-1.5 rounded-full bg-lime ring-1 ring-ink/10"></span></a>
                    <p class="mt-3 max-w-xs leading-relaxed text-mute">سماعات ومعدات صوت للمكتب والبيت، بالجنيه السوداني، والدفع بالبطاقة عبر MatmoomPay.</p>
                </div>
                <div>
                    <h2 class="mb-4 font-semibold">المتجر</h2>
                    <ul class="space-y-2.5 text-mute">
                        @foreach (\App\Models\Product::CATEGORIES as $key => $label)
                            <li><a href="{{ route('shop.index', ['category' => $key]) }}#products" class="hover:text-ink">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <h2 class="mb-4 font-semibold">الدعم</h2>
                    <ul class="space-y-2.5 text-mute">
                        <li><a href="{{ route('orders.index') }}" class="hover:text-ink">حالة الطلب</a></li>
                        <li>الدفع بالبطاقة</li>
                        <li>التوصيل إلى باب البيت</li>
                    </ul>
                </div>
                <div>
                    <h2 class="mb-4 font-semibold">حسابي</h2>
                    <ul class="space-y-2.5 text-mute">
                        <li><a href="{{ route('orders.index') }}" class="hover:text-ink">طلباتي</a></li>
                        <li><a href="{{ route('cart.show') }}" class="hover:text-ink">السلة</a></li>
                        <li><a href="{{ asset('images/CREDITS.md') }}" class="hover:text-ink">مصادر الصور</a></li>
                    </ul>
                </div>
            </div>
            <p class="mt-12 text-center text-xs text-mute">© {{ date('Y') }} متجر</p>
        </footer>
    </div>

    @if (session('status'))
        <div class="fixed inset-x-0 bottom-5 z-50 flex justify-center px-4" role="status">
            <p class="flex items-center gap-4 rounded-full bg-ink py-2.5 ps-5 pe-2.5 text-sm text-white shadow-[0_10px_30px_rgb(0_0_0/0.18)]">
                <span>{{ session('status') }}</span>
                <a href="{{ route('cart.show') }}" class="shrink-0 rounded-full bg-lime px-4 py-1.5 text-xs font-semibold text-ink">عرض السلة</a>
            </p>
        </div>
    @endif
</body>
</html>
