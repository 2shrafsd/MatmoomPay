@extends('shop.layout', ['title' => 'الطلبات'])

@section('content')
    @php
        $statusLabels = ['paid' => 'مدفوع', 'failed' => 'فشل', 'cancelled' => 'ملغى', 'expired' => 'انتهت مهلة الدفع', 'abandoned' => 'متروك'];
    @endphp

    <div class="px-2 pt-12 lg:px-6">
        <p class="eyebrow">حسابي</p>
        <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-5xl">طلباتي</h1>
        <p class="mt-3 text-mute">طلباتك من هذا المتصفح وحالة كل طلب.</p>
    </div>

    <div class="mt-8 overflow-x-auto rounded-[24px] bg-panel p-2 sm:p-3">
        <table class="w-full min-w-[38rem] text-sm">
            <thead class="text-xs text-mute">
                <tr>
                    <th class="px-4 py-3 text-right font-medium">الطلب</th>
                    <th class="px-4 py-3 text-right font-medium">العميل</th>
                    <th class="px-4 py-3 text-right font-medium">الإجمالي</th>
                    <th class="px-4 py-3 text-right font-medium">الحالة</th>
                    <th class="px-4 py-3 text-right font-medium">التاريخ</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr class="border-t border-line first:border-t-0">
                        <td class="px-4 py-4"><a href="{{ route('orders.show', $order) }}" class="font-semibold underline decoration-ink/20 underline-offset-4 hover:decoration-ink" dir="ltr">{{ $order->reference }}</a></td>
                        <td class="px-4 py-4">{{ $order->customer_name }}</td>
                        <td class="px-4 py-4 font-medium">{{ \App\Support\Sdg::format($order->total) }}</td>
                        <td class="px-4 py-4"><span @class(['rounded-full px-3 py-1 text-xs font-semibold', 'bg-lime' => $order->isPaid(), 'bg-white text-ink/70' => ! $order->isPaid()])>{{ $statusLabels[$order->status] ?? 'بانتظار الدفع' }}</span></td>
                        <td class="px-4 py-4 text-right text-mute" dir="ltr">{{ $order->created_at->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-16 text-center text-mute">لا توجد طلبات بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
