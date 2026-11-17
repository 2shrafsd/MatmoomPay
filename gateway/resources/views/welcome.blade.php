@php
    use App\Support\Money;

    $rate = rtrim(rtrim(number_format(config('gateway.fees.rate_bps') / 100, 2), '0'), '.');
    $signup = Route::has('register') ? route('register') : route('login');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="description" content="{{ __('Accept card payments in Sudanese pounds through a hosted checkout, payment links and a simple API.') }}">
        <meta name="theme-color" content="#5ce8a6">
        <title>{{ config('app.name') }} · {{ __('Online payments for Sudanese businesses') }}</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @fonts
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-dvh bg-mint-400 p-2 text-zinc-900 antialiased sm:p-4 lg:p-7">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-white focus:px-4 focus:py-2">{{ __('Skip to content') }}</a>

        <div class="overflow-hidden rounded-[1.75rem] bg-white">
            {{-- Header with the reference's faint column rules --}}
            <header class="relative border-b border-zinc-200/80">
                <div class="pointer-events-none absolute inset-y-0 start-[22%] hidden w-px bg-zinc-200/70 lg:block"></div>
                <div class="pointer-events-none absolute inset-y-0 end-[22%] hidden w-px bg-zinc-200/70 lg:block"></div>
                <nav class="relative mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-4 sm:px-8 lg:py-5" aria-label="{{ __('Main') }}">
                    <a href="{{ route('home') }}" class="flex items-center gap-2 text-xl font-semibold text-ink-900">
                        <x-app-logo-icon class="size-7 text-ink-800" />
                        {{ config('app.name') }}
                    </a>
                    <div class="hidden items-center gap-10 text-sm text-ink-900 lg:flex">
                        <a href="#products" class="hover:text-ink-600">{{ __('Products') }}</a>
                        <a href="#how" class="hover:text-ink-600">{{ __('How it works') }}</a>
                        <a href="#developers" class="hover:text-ink-600">{{ __('Developers') }}</a>
                        <a href="#pricing" class="hover:text-ink-600">{{ __('Pricing') }}</a>
                    </div>
                    <div class="flex items-center gap-1 sm:gap-2">
                        <x-locale-switch class="hidden sm:block" />
                        @auth
                            <a href="{{ route('dashboard') }}" class="inline-flex min-h-11 items-center rounded-full bg-ink-800 px-5 text-sm font-medium text-white hover:bg-ink-900">{{ __('Dashboard') }}</a>
                        @else
                            <a href="{{ route('login') }}" class="btn-ghost hidden sm:inline-flex">{{ __('Log in') }}</a>
                            <a href="{{ $signup }}" class="inline-flex min-h-11 items-center rounded-full bg-ink-800 px-5 text-sm font-medium text-white hover:bg-ink-900">{{ __('Get started') }}</a>
                        @endauth
                    </div>
                </nav>
            </header>

            <main id="main">
                {{-- Hero --}}
                <section class="relative overflow-hidden px-5 pt-14 pb-10 text-center sm:px-8 lg:pt-20">
                    <div class="rings pointer-events-none absolute start-1/2 top-[-12rem] size-[64rem] -translate-x-1/2 opacity-70 rtl:translate-x-1/2" aria-hidden="true"></div>
                    <div class="relative mx-auto max-w-4xl">
                        <a href="#how" class="pill-badge">
                            {{ __('Secure payment gateway') }}
                            <flux:icon.arrow-left variant="micro" class="ltr:rotate-180" />
                        </a>
                        <h1 class="display mt-7 text-[2.6rem] text-balance sm:text-6xl lg:text-[5.25rem]">
                            {{ __('Get paid online with confidence, every day') }}
                        </h1>
                        <p class="mx-auto mt-6 max-w-2xl text-base text-pretty text-zinc-600 sm:text-lg">
                            {{ __('Card payments in Sudanese pounds for online stores, apps and social sellers. Share a link, add a checkout, and watch every pound arrive in one dashboard.') }}
                        </p>
                        <div class="mt-9 flex flex-col items-center justify-center gap-5 sm:flex-row">
                            <a href="{{ $signup }}" class="btn-primary">
                                {{ __('Open a merchant account') }}
                                <flux:icon.chevron-left variant="micro" class="ltr:rotate-180" />
                            </a>
                            <a href="{{ route('docs') }}" class="text-base font-medium text-ink-900 underline-offset-4 hover:underline">{{ __('Read the API docs') }}</a>
                        </div>
                    </div>

                    {{-- Capability strip, in place of partner logos --}}
                    <ul class="relative mx-auto mt-14 flex max-w-6xl flex-wrap items-center justify-center gap-x-8 gap-y-4 text-sm font-medium text-zinc-500 sm:gap-x-10" aria-label="{{ __('What the gateway does') }}">
                        @foreach ([
                            ['credit-card', __('Visa & Mastercard')],
                            ['link', __('Payment links')],
                            ['qr-code', __('QR codes')],
                            ['arrow-path', __('Subscriptions')],
                            ['code-bracket', __('REST API')],
                            ['signal', __('Signed webhooks')],
                            ['arrow-uturn-left', __('Refunds')],
                            ['building-library', __('Settlements')],
                        ] as [$icon, $label])
                            <li class="flex items-center gap-2 whitespace-nowrap">
                                <flux:icon :name="$icon" variant="mini" class="text-zinc-400" />
                                {{ $label }}
                            </li>
                        @endforeach
                    </ul>
                </section>

                {{-- Photo cards with live UI overlays --}}
                <section class="px-3 pb-6 sm:px-6 lg:px-10" aria-label="{{ __('Product preview') }}">
                    <div class="mx-auto grid max-w-7xl gap-3 lg:grid-cols-[minmax(0,0.6fr)_minmax(0,1fr)] lg:gap-4">
                        <figure class="relative aspect-[4/5] overflow-hidden rounded-[2rem] bg-sky-200 lg:aspect-auto lg:h-[30rem]">
                            <img src="{{ asset('images/hero-portrait.webp') }}" alt="{{ __('A young Sudanese woman in a mint green toub smiling under a clear sky') }}" class="absolute inset-0 size-full object-cover object-top" width="1122" height="1402" fetchpriority="high">
                            <span class="absolute end-5 top-5 inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm text-ink-900 shadow-sm">
                                {{ __('Monthly') }}
                                <flux:icon.chevron-down variant="micro" />
                            </span>
                            <figcaption class="mint-card absolute start-5 bottom-5 px-5 py-4">
                                <span class="ltr-nums block text-start text-2xl font-semibold sm:text-3xl">{{ Money::format(4_825_075) }}</span>
                                <span class="mt-1 block text-sm">{{ __('Collected in October') }}</span>
                            </figcaption>
                        </figure>

                        <figure class="relative aspect-[4/3] overflow-hidden rounded-[2rem] bg-sky-200 lg:aspect-auto lg:h-[30rem]">
                            <img src="{{ asset('images/hero-group.webp') }}" alt="{{ __('Four young Sudanese friends laughing together on a sunny hill') }}" class="absolute inset-0 size-full object-cover" width="1672" height="941" loading="lazy">
                            <div class="absolute top-6 right-6 hidden max-w-xs text-start text-white drop-shadow sm:block">
                                <span class="mb-3 flex gap-1.5" aria-hidden="true"><span class="size-2 rounded-full bg-white"></span><span class="size-2 rounded-full bg-white/50"></span></span>
                                <p class="text-sm leading-relaxed">{{ __('One gateway for checkout, links and subscriptions, built for how people in Sudan buy and sell today.') }}</p>
                            </div>
                            <figcaption class="ink-card absolute right-4 bottom-4 w-[min(22rem,calc(100%-2rem))] p-5 text-start sm:right-6 sm:bottom-6">
                                <p class="text-base font-medium">{{ __('Recent transactions') }}</p>
                                <div class="mt-4 flex items-center justify-between gap-3 text-xs">
                                    <span class="ltr-nums font-mono text-mint-400">#2610201031</span>
                                    <span class="text-white/85">{{ __('Payment from Nile Aroma') }}</span>
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-3 text-xs">
                                    <span class="inline-flex items-center gap-1.5 text-mint-400"><span class="grid size-4 place-items-center rounded-full bg-mint-400/20"><flux:icon.arrow-up variant="micro" class="size-3" /></span>{{ __('Successful') }}</span>
                                    <span class="ltr-nums text-white/85">{{ Money::format(18_500) }}</span>
                                </div>
                            </figcaption>
                        </figure>
                    </div>
                </section>

                {{-- "Manage your money" with the checkout widget --}}
                <section id="how" class="px-5 pt-20 text-center sm:px-8">
                    <p class="eyebrow">{{ __('Smart payments') }}</p>
                    <h2 class="display mx-auto mt-5 max-w-3xl text-4xl sm:text-6xl">{{ __('Every payment, in one place') }}</h2>
                    <p class="mx-auto mt-5 max-w-xl text-zinc-600">{{ __('Your customer pays on our secure page, the bank confirms with a one-time code, and your order system hears about it in seconds.') }}</p>
                </section>

                <section class="px-3 pt-12 sm:px-6 lg:px-10">
                    <div class="mint-wash mx-auto grid max-w-7xl gap-10 overflow-hidden rounded-[2rem] px-5 py-12 sm:px-10 lg:grid-cols-2 lg:items-center lg:py-16">
                        <ol class="order-2 space-y-6 text-start lg:order-1">
                            @foreach ([
                                [__('Create a checkout session'), __('Send the products and your return links from your server. Amounts are whole Sudanese pounds, with no decimals.')],
                                [__('Your customer pays'), __('They land on a fast payment page in Arabic or English and confirm with their bank.')],
                                [__('You fulfil the order'), __('A signed webhook tells your system, and the money waits in your next settlement.')],
                            ] as $index => [$title, $body])
                                <li class="flex gap-4">
                                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-ink-800 text-sm font-semibold text-mint-400">{{ $index + 1 }}</span>
                                    <div>
                                        <h3 class="text-lg font-medium text-ink-900">{{ $title }}</h3>
                                        <p class="mt-1 text-zinc-600">{{ $body }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>

                        <div class="order-1 mx-auto w-full max-w-md lg:order-2" aria-hidden="true">
                            <div class="ink-card px-6 py-6 text-center">
                                <p class="text-white/80">{{ __('Amount (:currency)', ['currency' => Money::currency()]) }}</p>
                                <div class="mt-4 flex items-center justify-between">
                                    <span class="grid size-11 place-items-center rounded-full bg-white text-ink-900"><flux:icon.minus variant="mini" /></span>
                                    <span class="ltr-nums text-5xl font-medium tracking-tight">75,000</span>
                                    <span class="grid size-11 place-items-center rounded-full bg-white text-ink-900"><flux:icon.plus variant="mini" /></span>
                                </div>
                            </div>
                            <div class="surface mt-3 p-5 text-start">
                                <p class="text-lg font-medium text-ink-900">{{ __('Pay to') }}</p>
                                <div class="mt-4 flex items-center gap-3">
                                    <span class="grid size-12 place-items-center rounded-full bg-zinc-100 font-semibold text-ink-900">NA</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-ink-900">Nile Aroma</p>
                                        <p class="ltr-nums text-start text-sm text-zinc-500">{{ __('Invoice') }} #2610201031</p>
                                    </div>
                                    <span class="rounded-full bg-mint-100 px-4 py-2 text-sm font-medium text-ink-900">{{ __('Change') }}</span>
                                </div>
                                <div class="mt-4 rounded-2xl bg-zinc-50 px-4 py-3 text-zinc-400">{{ __('Add a note') }}</div>
                                <div class="btn-primary mt-5 w-full">{{ __('Pay') }} <span class="ltr-nums">{{ Money::format(75_000) }}</span></div>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Products --}}
                <section id="products" class="px-5 pt-24 sm:px-8">
                    <div class="mx-auto max-w-7xl">
                        <div class="max-w-2xl">
                            <p class="eyebrow">{{ __('Products') }}</p>
                            <h2 class="display mt-4 text-4xl sm:text-5xl">{{ __('One account for every way you get paid') }}</h2>
                        </div>
                        <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ([
                                ['shopping-cart', __('Hosted checkout'), __('Create a session from your server and redirect the customer. We handle the card form, the bank code and 3-D Secure.')],
                                ['link', __('Payment links and QR codes'), __('A fixed amount or one the customer types. Share it on WhatsApp or print the QR code at your counter.')],
                                ['arrow-path', __('Subscriptions'), __('Monthly or yearly plans charged to a saved card, with automatic retries when a renewal fails.')],
                                ['signal', __('Signed webhooks'), __('A POST the moment a payment succeeds or fails, signed with HMAC-SHA256 so you know it came from us.')],
                                ['arrow-uturn-left', __('Refunds in one click'), __('Refund all or part of a payment. The ledger and the next settlement update on their own.')],
                                ['user-group', __('A dashboard for the whole team'), __('Separate roles for your accountant and your developer, and an audit log of every sensitive change.')],
                            ] as [$icon, $title, $body])
                                <article class="rounded-[1.75rem] border border-zinc-200 bg-white p-7 transition hover:border-ink-800/30 hover:shadow-(--shadow-card)">
                                    <span class="grid size-12 place-items-center rounded-2xl bg-mint-100 text-ink-800"><flux:icon :name="$icon" /></span>
                                    <h3 class="mt-5 text-lg font-medium text-ink-900">{{ $title }}</h3>
                                    <p class="mt-2 text-sm leading-relaxed text-zinc-600">{{ $body }}</p>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- Merchants --}}
                <section class="px-3 pt-24 sm:px-6 lg:px-10">
                    <div class="mx-auto grid max-w-7xl gap-6 lg:grid-cols-2 lg:items-stretch">
                        <figure class="relative min-h-[26rem] overflow-hidden rounded-[2rem] bg-mint-100">
                            <img src="{{ asset('images/merchant.webp') }}" alt="{{ __('A Sudanese shop owner smiling behind his counter with a phone in his hand') }}" class="absolute inset-0 size-full object-cover" width="1122" height="1402" loading="lazy">
                            <figcaption class="surface absolute start-5 bottom-5 flex items-center gap-3 px-4 py-3">
                                <span class="grid size-10 place-items-center rounded-full bg-mint-400 text-ink-900"><flux:icon.check variant="mini" /></span>
                                <span class="text-start text-sm"><span class="block font-medium text-ink-900">{{ __('Payment received') }}</span><span class="ltr-nums block text-zinc-500">{{ Money::format(14_500) }} · •••• 4242</span></span>
                            </figcaption>
                        </figure>
                        <div class="flex flex-col justify-center rounded-[2rem] bg-ink-900 p-8 text-white sm:p-12">
                            <p class="eyebrow text-mint-400">{{ __('For merchants') }}</p>
                            <h2 class="mt-4 text-3xl font-medium sm:text-4xl">{{ __('From a small shop to a large online store') }}</h2>
                            <ul class="mt-8 space-y-4 text-white/85">
                                @foreach ([__('Test mode the moment you sign up, no paperwork needed to start building'), __('Live payments after we review your documents'), __('Settlements to your bank account, with every fee shown'), __('Arabic and English everywhere, for you and your customers')] as $point)
                                    <li class="flex items-start gap-3"><flux:icon.check-circle variant="mini" class="mt-0.5 shrink-0 text-mint-400" />{{ $point }}</li>
                                @endforeach
                            </ul>
                            <a href="{{ $signup }}" class="btn-mint mt-10 self-start">{{ __('Open a merchant account') }}</a>
                        </div>
                    </div>
                </section>

                {{-- Developers --}}
                <section id="developers" class="px-5 pt-24 sm:px-8">
                    <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-2 lg:items-center">
                        <div>
                            <p class="eyebrow">{{ __('Developers') }}</p>
                            <h2 class="display mt-4 text-4xl sm:text-5xl">{{ __('Three API calls from cart to paid') }}</h2>
                            <p class="mt-5 text-zinc-600">{{ __('A clear JSON API, test keys from the first minute, idempotent requests and signed webhooks. Test cards work exactly like real ones.') }}</p>
                            <a href="{{ route('docs') }}" class="btn-secondary mt-8">{{ __('See the full API reference') }}</a>
                        </div>
                        <div class="overflow-hidden rounded-[1.75rem] bg-ink-950 text-sm shadow-(--shadow-lift)" dir="ltr">
                            <div class="flex items-center gap-2 border-b border-white/10 px-5 py-3 text-xs text-white/50">
                                <span class="size-2.5 rounded-full bg-mint-400"></span><span class="size-2.5 rounded-full bg-white/25"></span><span class="size-2.5 rounded-full bg-white/25"></span>
                                <span class="ms-2">POST /api/v1/checkout/session</span>
                            </div>
<pre class="overflow-x-auto p-5 leading-relaxed text-white/90"><code>curl {{ url('/api/v1/checkout/session') }} \
  -H "matmoom-api-key: sk_test_..." \
  -d '{
    "client_reference_id": "order-1001",
    "mode": "payment",
    "products": [{ "name": "Karkade, 1 kg",
      "quantity": 2, "unit_amount": 12000 }],
    "success_url": "https://shop.sd/thanks",
    "cancel_url": "https://shop.sd/cart",
    "metadata": { "Customer name": "Amna" }
  }'

<span class="text-mint-400">→ 200</span> { "success": true, "code": 2001,
  "data": { "session_id": "checkout_8f2c…",
            "total_amount": 24000 } }</code></pre>
                        </div>
                    </div>
                </section>

                {{-- Security --}}
                <section class="px-3 pt-24 sm:px-6 lg:px-10">
                    <div class="mx-auto max-w-7xl rounded-[2rem] bg-mint-400 p-8 text-ink-950 sm:p-12">
                        <h2 class="display max-w-2xl text-3xl sm:text-5xl">{{ __('Card data stays away from your servers') }}</h2>
                        <div class="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ([
                                ['lock-closed', __('Hosted card form'), __('Customers type their card on our page or their bank\'s, never on your site.')],
                                ['finger-print', __('Bank confirmation'), __('A one-time code or 3-D Secure before any card is charged.')],
                                ['key', __('Scoped keys'), __('Separate test and live keys, stored hashed and revocable at any time.')],
                                ['shield-check', __('Two-factor sign in'), __('Protect the dashboard with an authenticator app or a passkey.')],
                            ] as [$icon, $title, $body])
                                <div>
                                    <span class="grid size-11 place-items-center rounded-full bg-ink-900 text-mint-400"><flux:icon :name="$icon" variant="mini" /></span>
                                    <h3 class="mt-4 font-medium">{{ $title }}</h3>
                                    <p class="mt-1.5 text-sm leading-relaxed text-ink-900/75">{{ $body }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- Pricing --}}
                <section id="pricing" class="px-5 py-24 text-center sm:px-8">
                    <p class="eyebrow">{{ __('Pricing') }}</p>
                    <h2 class="display mx-auto mt-4 max-w-2xl text-4xl sm:text-5xl">{{ __('Pay only when you get paid') }}</h2>
                    <p class="mx-auto mt-4 max-w-lg text-zinc-600">{{ __('No setup fee and no monthly fee. One simple rate on successful payments.') }}</p>
                    <div class="surface mx-auto mt-10 max-w-md p-8 text-start">
                        <p class="ltr-nums text-start text-6xl font-medium tracking-tight text-ink-900">{{ $rate }}%</p>
                        <p class="mt-2 text-zinc-500">{{ __('per successful payment') }}</p>
                        <ul class="mt-6 space-y-3 text-sm">
                            @foreach ([__('Hosted checkout, links and QR codes'), __('Subscriptions and saved cards'), __('Unlimited team members'), __('Test mode with no time limit')] as $feature)
                                <li class="flex items-center gap-2"><span class="grid size-5 place-items-center rounded-full bg-mint-400"><flux:icon.check variant="micro" class="size-3 text-ink-900" /></span>{{ $feature }}</li>
                            @endforeach
                        </ul>
                        <a href="{{ $signup }}" class="btn-primary mt-8 w-full">{{ __('Open a free account') }}</a>
                    </div>
                </section>
            </main>

            <footer class="border-t border-zinc-200 px-5 py-10 sm:px-8">
                <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 text-sm text-zinc-500 sm:flex-row">
                    <p class="flex items-center gap-2"><x-app-logo-icon class="size-5 text-ink-800" /> © {{ now()->year }} {{ config('app.name') }}</p>
                    <div class="flex items-center gap-5">
                        <a href="{{ route('docs') }}" class="hover:text-ink-900">{{ __('API docs') }}</a>
                        <a href="{{ route('login') }}" class="hover:text-ink-900">{{ __('Log in') }}</a>
                        <x-locale-switch class="sm:hidden" />
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>
