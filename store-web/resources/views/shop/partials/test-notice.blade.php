{{-- The shop runs against the gateway's test mode: every payment uses the test card below. --}}
<p class="{{ $class ?? '' }} flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-center font-medium text-ink">
    <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6m-5 0v6.5L4.8 18.2A1.8 1.8 0 0 0 6.3 21h11.4a1.8 1.8 0 0 0 1.5-2.8L14 9.5V3M7.5 15h9"/></svg>
    <span class="whitespace-nowrap font-semibold">وضع الاختبار</span>
    <span aria-hidden="true" class="{{ ($stacked ?? false) ? 'hidden' : 'max-sm:hidden' }}">·</span>
    <span class="whitespace-nowrap">بطاقة <span dir="ltr" class="font-mono font-semibold tracking-wide">4242 4242 4242 4242</span></span>
    <span aria-hidden="true" class="{{ ($stacked ?? false) ? 'hidden' : 'max-sm:hidden' }}">·</span>
    <span class="whitespace-nowrap">رمز التحقق <span dir="ltr" class="font-mono font-semibold">1234</span></span>
</p>
