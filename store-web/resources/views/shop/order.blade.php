@extends('shop.layout', ['title' => 'الطلب '.$order->reference])

@section('content')
    @php
        $label = [
            'paid' => 'مدفوع',
            'failed' => 'فشل',
            'cancelled' => 'ملغى',
            'expired' => 'انتهت مهلة الدفع',
            'abandoned' => 'متروك',
        ][$order->status] ?? 'بانتظار الدفع';
    @endphp

    <div class="mx-auto max-w-3xl pt-12">
        <a href="{{ route('orders.index') }}" class="text-sm text-mute hover:text-ink">→ كل الطلبات</a>

        <div class="mt-5 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">الطلب</p>
                <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl" dir="ltr">{{ $order->reference }}</h1>
            </div>
            <span @class(['rounded-full px-4 py-1.5 text-sm font-semibold', 'bg-lime' => $order->isPaid(), 'bg-panel text-ink/70' => ! $order->isPaid()])>{{ $label }}</span>
        </div>

        @if (session('notice'))
            <p class="mt-6 rounded-[20px] bg-panel px-5 py-3 text-sm" role="status">{{ session('notice') }}</p>
        @endif

        <p class="mt-6 text-mute">
            @if ($order->isPaid())
                <span class="text-ink">شكرًا {{ $order->customer_name }}، وصلتنا دفعتك وسنجهز طلبك.</span>
            @else
                لم تكتمل الدفعة بعد. إن كنت دفعت للتو فحدّث الصفحة بعد لحظات.
            @endif
        </p>

        <div class="mt-8 rounded-[24px] bg-panel p-3 sm:p-4">
            <ul class="space-y-2">
                @foreach ($order->items as $item)
                    <li class="flex items-center gap-4 rounded-[18px] bg-white/60 p-2 pe-4">
                        <div class="w-16 shrink-0 overflow-hidden rounded-[12px] bg-[#ebe8e3]">
                            <x-product-photo :product="$products->get($item['product_id'])" size="thumb" class="mix-blend-multiply" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold">{{ $item['name'] }}</p>
                            <p class="mt-0.5 text-xs text-mute">{{ $item['quantity'] }} × {{ \App\Support\Sdg::format($item['unit_amount']) }}</p>
                        </div>
                        <span class="text-sm font-semibold">{{ \App\Support\Sdg::format($item['quantity'] * $item['unit_amount']) }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="flex items-baseline justify-between px-3 pt-5 pb-2">
                <span class="text-mute">الإجمالي</span>
                <span class="text-xl font-bold">{{ \App\Support\Sdg::format($order->total) }}</span>
            </div>
        </div>

        <dl class="mt-6 space-y-1.5 px-2 text-xs text-mute">
            <div>تاريخ الطلب: <span dir="ltr">{{ $order->created_at->format('Y-m-d H:i') }}</span></div>
            @if ($order->invoice)<div>فاتورة البوابة: <span dir="ltr">#{{ $order->invoice }}</span></div>@endif
            @if ($order->session_id)<div>جلسة الدفع: <code dir="ltr">{{ $order->session_id }}</code></div>@endif
        </dl>

        <a href="{{ route('shop.index') }}#products" class="mt-10 inline-block rounded-full bg-lime px-6 py-3 text-sm font-semibold">العودة إلى المتجر</a>
    </div>
@endsection
