@extends('shop.layout', ['title' => 'السلة'])

@section('content')
    <div class="px-2 pt-12 lg:px-6">
        <p class="eyebrow">السلة</p>
        <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-5xl">سلة المشتريات</h1>
    </div>

    @if ($lines === [])
        <div class="mt-8 rounded-[24px] bg-panel py-20 text-center">
            <p class="text-mute">السلة فارغة.</p>
            <a href="{{ route('shop.index') }}#products" class="mt-5 inline-block rounded-full bg-lime px-6 py-3 text-sm font-semibold">تصفّح المنتجات</a>
        </div>
    @else
        <div class="mt-8 grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_26rem]">
            <ul class="min-w-0 space-y-3">
                @foreach ($lines as $line)
                    <li class="flex gap-3 rounded-[24px] bg-panel p-3 sm:gap-5">
                        <div class="w-20 shrink-0 self-start overflow-hidden rounded-[16px] bg-[#ebe8e3] sm:w-28">
                            <x-product-photo :product="$line['product']" size="thumb" class="mix-blend-multiply" />
                        </div>
                        <div class="flex min-w-0 flex-1 flex-col justify-center gap-4 py-1 pe-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="font-semibold leading-snug">{{ $line['product']->name }}</p>
                                <p class="mt-1 text-sm text-mute">{{ \App\Support\Sdg::format($line['product']->price) }} للقطعة</p>
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 sm:justify-end">
                                <form method="POST" action="{{ route('cart.update', $line['product']) }}" class="flex items-center gap-1 rounded-full bg-white p-1">
                                    @csrf
                                    @method('PATCH')
                                    <label class="sr-only" for="quantity-{{ $line['product']->id }}">الكمية</label>
                                    <input id="quantity-{{ $line['product']->id }}" type="number" name="quantity" value="{{ $line['quantity'] }}" min="0" max="10"
                                           class="w-14 rounded-full bg-transparent py-1 text-center text-sm font-medium focus:outline-none focus:ring-2 focus:ring-lime">
                                    <button class="rounded-full px-3 py-1 text-xs font-medium text-mute hover:bg-panel hover:text-ink">تحديث</button>
                                </form>
                                <span class="text-left font-semibold sm:w-28">{{ \App\Support\Sdg::format($line['subtotal']) }}</span>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <form method="POST" action="{{ route('checkout') }}" class="space-y-5 rounded-[24px] bg-panel p-6 sm:p-7 lg:sticky lg:top-6">
                @csrf
                <h2 class="text-lg font-bold">بيانات الطلب</h2>
                <label class="block text-sm font-medium">الاسم
                    <input name="customer_name" value="{{ old('customer_name') }}" required autocomplete="name"
                           class="mt-1.5 block w-full rounded-2xl border border-line bg-white px-4 py-3 text-sm focus:border-ink/40 focus:outline-none">
                </label>
                <label class="block text-sm font-medium">رقم الهاتف
                    <input name="customer_phone" value="{{ old('customer_phone', '+249 ') }}" dir="ltr" required autocomplete="tel"
                           class="mt-1.5 block w-full rounded-2xl border border-line bg-white px-4 py-3 text-left text-sm focus:border-ink/40 focus:outline-none">
                </label>
                <div class="flex items-baseline justify-between border-t border-line pt-5">
                    <span class="text-mute">الإجمالي</span>
                    <span class="text-xl font-bold">{{ \App\Support\Sdg::format($total) }}</span>
                </div>
                @include('shop.partials.test-notice', ['class' => 'rounded-[18px] bg-white px-4 py-3 text-[15px] ring-2 ring-lime', 'stacked' => true])
                <button class="w-full rounded-full bg-lime px-4 py-3.5 font-semibold text-ink transition hover:brightness-95 active:scale-[0.99]">ادفع بالبطاقة</button>
                <p class="text-center text-xs leading-relaxed text-mute">تنتقل إلى صفحة الدفع في MatmoomPay، ثم تعود إلى هنا بعد الدفع.</p>
            </form>
        </div>
    @endif
@endsection
