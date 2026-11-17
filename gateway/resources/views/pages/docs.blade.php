@php
    $base = url('/api/v1');
    $endpoints = [
        __('Checkout sessions') => [
            ['GET', '/checkout/session', __('List sessions, newest first. Query: limit (1–100), skip.')],
            ['POST', '/checkout/session', __('Create a session and get the session_id to redirect your customer.')],
            ['GET', '/checkout/session/{session_id}', __('Retrieve a session.')],
            ['GET', '/checkout/reference/{client_reference_id}', __('Retrieve the latest session for your own reference.')],
            ['GET', '/checkout/invoice/{invoice}', __('Retrieve a session by its invoice number.')],
            ['POST', '/checkout/{session_id}/cancel', __('Cancel an unpaid session.')],
        ],
        __('Customers') => [
            ['GET', '/customers', __('List customers. Query: limit, skip.')],
            ['POST', '/customers', __('Create a customer so they can save cards. Body: client_customer_id.')],
            ['GET', '/customers/{customer_id}', __('Retrieve a customer.')],
            ['DELETE', '/customers/{customer_id}', __('Delete a customer and their saved cards. Refused while they have an active subscription; past payments and invoices are kept.')],
        ],
        __('Payment methods') => [
            ['GET', '/payment_methods?customer_id=', __('List a customer\'s saved cards.')],
            ['DELETE', '/payment_methods/{card_id}', __('Delete a saved card.')],
        ],
        __('Payment intents') => [
            ['GET', '/payment_intents', __('List payment intents. Query: limit, skip.')],
            ['POST', '/payment_intents', __('Create an intent to charge a saved card off-session.')],
            ['POST', '/payment_intents/{id}/confirm', __('Charge the card. May return requires_action with next_action.url.')],
            ['POST', '/payment_intents/{id}/cancel', __('Cancel an intent that has not succeeded.')],
            ['GET', '/payment_intents/{id}', __('Retrieve an intent.')],
            ['GET', '/payment_intents/{client_reference_id}/reference', __('Retrieve an intent by your reference.')],
        ],
        __('Payments') => [
            ['GET', '/payments', __('List payments. Query: limit, skip, checkout_invoice, payment_intent.')],
            ['GET', '/payments/{payment_id}', __('Retrieve a payment with its refunds.')],
        ],
        __('Plans and subscriptions') => [
            ['GET', '/plans', __('List plans.')],
            ['POST', '/plans', __('Create a plan. Body: name, amount, interval (week, month, year), interval_count.')],
            ['GET', '/plans/{plan_id}', __('Retrieve a plan.')],
            ['GET', '/subscriptions', __('List subscriptions. Query: limit, skip.')],
            ['GET', '/subscriptions/{id}', __('Retrieve a subscription.')],
            ['GET', '/subscriptions/{id}/invoices', __('List the invoices of a subscription with attempt_count and next_payment_attempt.')],
            ['POST', '/subscriptions/{id}/cancel', __('Cancel now, or at the end of the period with at_period_end: true.')],
        ],
        __('Refunds') => [
            ['GET', '/refunds', __('List refunds. Query: limit, skip.')],
            ['POST', '/refunds', __('Refund a payment. Body: payment_id, reason, metadata, optional amount for a partial refund. The status is successful, failed, or pending while the bank confirms.')],
            ['GET', '/refunds/{refund_id}', __('Retrieve a refund.')],
        ],
    ];
    $toc = ['introduction' => __('Introduction'), 'authentication' => __('Authentication'), 'responses' => __('Responses and errors'), 'checkout' => __('Accepting a payment'), 'statuses' => __('Statuses'), 'reference' => __('Endpoint reference'), 'intents' => __('Charging saved cards'), 'subscriptions' => __('Subscriptions'), 'webhooks' => __('Webhooks'), 'idempotency' => __('Safe retries'), 'testing' => __('Test cards')];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title>{{ __('API documentation') }} · {{ config('app.name') }}</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @fonts
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-dvh bg-mint-400 p-2 text-zinc-900 antialiased sm:p-4">
        <div class="min-h-[calc(100dvh-1rem)] overflow-hidden rounded-[1.75rem] bg-white sm:min-h-[calc(100dvh-2rem)]">
        <header class="sticky top-0 z-40 border-b border-zinc-200/80 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-bold">
                    <x-app-logo-icon class="size-7 text-ink-800" />
                    {{ config('app.name') }} <span class="font-normal text-zinc-500">{{ __('Docs') }}</span>
                </a>
                <div class="flex items-center gap-2">
                    <x-locale-switch />
                    <a href="{{ route('dashboard') }}" class="inline-flex min-h-10 items-center rounded-full bg-ink-800 px-5 text-sm font-medium text-white hover:bg-ink-900">{{ __('Dashboard') }}</a>
                </div>
            </div>
        </header>

        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-10 sm:px-6 lg:grid-cols-[14rem_minmax(0,1fr)]">
            <nav class="hidden lg:block" aria-label="{{ __('On this page') }}">
                <ul class="sticky top-24 space-y-1 text-sm">
                    @foreach ($toc as $anchor => $label)
                        <li><a href="#{{ $anchor }}" class="block rounded-full px-4 py-2 text-zinc-600 hover:bg-mint-100 hover:text-ink-900">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>

            <main class="min-w-0 space-y-16 [&_h2]:scroll-mt-24 [&_h2]:text-3xl [&_h2]:font-medium [&_h2]:text-ink-900 [&_h3]:mt-8 [&_h3]:font-medium [&_h3]:text-ink-900 [&_p]:mt-3 [&_p]:leading-relaxed [&_p]:text-zinc-600  [&_pre]:mt-4 [&_pre]:overflow-x-auto [&_pre]:rounded-2xl [&_pre]:bg-ink-950 [&_pre]:p-4 [&_pre]:text-sm [&_pre]:leading-relaxed [&_pre]:text-zinc-100">
                <section>
                    <span class="pill-badge">{{ __('Developers') }}</span>
                    <h1 class="display mt-5 text-4xl sm:text-6xl">{{ __('API documentation') }}</h1>
                    <p class="text-lg">{{ __('Everything you need to accept card payments with checkout sessions, charge saved cards and receive webhooks.') }}</p>
                </section>

                <section id="introduction">
                    <h2>{{ __('Introduction') }}</h2>
                    <p>{{ __('The API is organised around JSON over HTTPS. All amounts are whole Sudanese pounds sent as integers, with no decimals: SDG 18,500 is sent as 18500.') }}</p>
                    <pre dir="ltr"><code>{{ $base }}</code></pre>
                    <p>{{ __('The same base URL serves test and live mode. The key you send decides the mode: keys starting with sk_test_ only see test data and only accept test cards.') }}</p>
                    <p>{{ __('Import the OpenAPI description into Postman, Insomnia or a code generator:') }} <a href="{{ url('/openapi.json') }}" class="font-medium text-ink-900 underline underline-offset-4" dir="ltr">{{ url('/openapi.json') }}</a></p>
                    <p>{{ __('Every API response carries an X-Request-Id header. Include it when you contact support about a request.') }}</p>
                </section>

                <section id="authentication">
                    <h2>{{ __('Authentication') }}</h2>
                    <p>{{ __('Send your secret key in the matmoom-api-key header. Create keys under Developers, API keys in the dashboard. Secret keys belong on your server only; the publishable key is the one you put in the payment page link.') }}</p>
<pre dir="ltr"><code>curl {{ $base }}/checkout/session?limit=10 \
  -H "matmoom-api-key: sk_test_xxxxxxxxxxxxxxxxxxxx"</code></pre>
                    <p>{{ __('An Authorization: Bearer header with the same key also works.') }}</p>
                    <p>{{ __('Requests are limited to :count per minute per key and :ip per minute per IP address. Above that you receive HTTP 429; retry with exponential back-off.', ['count' => config('gateway.api.rate_limit_per_minute'), 'ip' => config('gateway.api.ip_rate_limit_per_minute')]) }}</p>
                </section>

                <section id="responses">
                    <h2>{{ __('Responses and errors') }}</h2>
                    <p>{{ __('Every response uses the same envelope. Check success first, then read code and data.') }}</p>
<pre dir="ltr"><code>{ "success": true, "code": 2000, "description": "object retrieved successfully", "data": { ... } }

{ "success": false, "code": 4000, "description": "Invalid information",
  "data": { "error": [ { "field": "products.0.unit_amount", "message": "..." } ] } }</code></pre>
                    <div class="mt-6 overflow-x-auto">
                        <table class="w-full text-start text-sm">
                            <thead class="border-b border-zinc-200 text-zinc-500 "><tr><th class="py-2 pe-4 text-start font-medium">{{ __('Code') }}</th><th class="py-2 pe-4 text-start font-medium">{{ __('HTTP') }}</th><th class="py-2 text-start font-medium">{{ __('Meaning') }}</th></tr></thead>
                            <tbody class="divide-y divide-zinc-200 ">
                                @foreach ([
                                    [2000, 200, 'object retrieved successfully'],
                                    [2001, 200, 'object created successfully'],
                                    [2002, 200, 'object updated successfully'],
                                    [2003, 200, 'object removed successfully'],
                                    [4000, 400, 'Invalid information (see data.error for each field)'],
                                    [4003, 404, 'object not found'],
                                    [4004, '400 / 409', 'Object already exists, or a request with this Idempotency-Key is still running (409)'],
                                    [4006, 400, 'Date expired'],
                                    [4010, 401, 'No valid API key provided'],
                                    [4030, 403, 'Account not allowed in this mode'],
                                    [4050, 405, 'This HTTP method is not supported for this endpoint'],
                                    [4101, '400 / 409', 'Unable to update object, or a payment is already being processed (409)'],
                                    [4200, '402 / 503', 'Payment method rejected, or the card processor did not answer (503: do not retry the payment, wait for the webhook)'],
                                    [4290, 429, 'Too many requests'],
                                    [4300, 400, 'Refund is not allowed'],
                                    [4301, 400, 'Payment already refunded, or a refund is still pending'],
                                    [5000, 500, 'Something went wrong on our side'],
                                ] as [$code, $http, $meaning])
                                    <tr><td class="ltr-nums py-2 pe-4 font-mono">{{ $code }}</td><td class="ltr-nums py-2 pe-4 font-mono">{{ $http }}</td><td class="py-2" dir="ltr">{{ $meaning }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="checkout">
                    <h2>{{ __('Accepting a payment') }}</h2>
                    <h3>{{ __('1. Create a checkout session') }}</h3>
                    <p>{{ __('The total is calculated by us from quantity × unit_amount. Up to 100 products, names up to 40 characters. Sessions expire after 24 hours unless you set expire_in_minutes (30 to 10080).') }}</p>
<pre dir="ltr"><code>POST {{ $base }}/checkout/session
{
  "client_reference_id": "order-1001",
  "mode": "payment",
  "products": [ { "name": "Karkade, 1 kg", "quantity": 2, "unit_amount": 12000 } ],
  "success_url": "https://your-store.sd/orders/1001/thanks",
  "cancel_url": "https://your-store.sd/cart",
  "customer_id": "cus_...",            // optional, lets the customer use saved cards
  "save_card_on_success": false,       // optional
  "metadata": { "Customer name": "Amna Osman", "Phone": "+249 91 234 5678" }
}</code></pre>
                    <p>{{ __('metadata is required on sessions, intents and refunds: send an empty object {} when you have nothing to attach. client_reference_id does not have to be unique; looking a session up by it returns the newest one, so send an Idempotency-Key when you retry.') }}</p>
                    <h3>{{ __('2. Redirect the customer') }}</h3>
<pre dir="ltr"><code>{{ url('/pay') }}/{session_id}?key={publishable_key}</code></pre>
                    <p>{{ __('After paying, the customer returns to your success_url. If they give up, they return to cancel_url; the session stays open so they can come back, and when it runs out you receive checkout.expired.') }}</p>
                    <h3>{{ __('3. Confirm before you fulfil') }}</h3>
                    <p>{{ __('Never trust the redirect alone. Wait for the checkout.completed webhook or fetch the session and check that payment_status is paid.') }}</p>
                </section>

                <section id="statuses">
                    <h2>{{ __('Statuses') }}</h2>
                    <p>{{ __('Status values keep the casing of the original contract, so compare them exactly as listed.') }}</p>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-zinc-200">
                                @foreach ([
                                    __('Checkout session (payment_status)') => 'unpaid · paid · cancelled · expired',
                                    __('Payment (status)') => 'InProcess · Successful · Failed',
                                    __('Payment intent (status)') => 'requires_payment_method · requires_confirmation · requires_action · succeeded · cancelled',
                                    __('Refund (status)') => 'pending · successful · failed',
                                    __('Subscription (status)') => 'active · past_due · cancelled',
                                ] as $object => $values)
                                    <tr><td class="py-2 pe-4 font-medium">{{ $object }}</td><td class="py-2 font-mono" dir="ltr">{{ $values }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p>{{ __('Lists return up to limit objects (default 10, at most 100) starting at skip, newest first. Keep increasing skip until a page holds fewer than limit objects.') }}</p>
                </section>

                <section id="reference">
                    <h2>{{ __('Endpoint reference') }}</h2>
                    @foreach ($endpoints as $group => $rows)
                        <h3>{{ $group }}</h3>
                        <ul class="mt-3 divide-y divide-zinc-200 rounded-xl border border-zinc-200  ">
                            @foreach ($rows as [$method, $path, $description])
                                <li class="flex flex-col gap-1 p-3 sm:flex-row sm:items-center sm:gap-4">
                                    <span class="flex min-w-0 items-center gap-2" dir="ltr">
                                        <span @class(['w-16 shrink-0 rounded-md px-2 py-0.5 text-center font-mono text-xs font-semibold', 'bg-sky-100 text-sky-800  ' => $method === 'GET', 'bg-green-100 text-green-800  ' => $method === 'POST', 'bg-red-100 text-red-800  ' => $method === 'DELETE'])>{{ $method }}</span>
                                        <code class="truncate font-mono text-sm">{{ $path }}</code>
                                    </span>
                                    <span class="text-sm text-zinc-600 sm:ms-auto sm:text-end ">{{ $description }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                </section>

                <section id="intents">
                    <h2>{{ __('Charging saved cards') }}</h2>
                    <p>{{ __('Create a customer, pass customer_id when creating a session, and the customer can save the card on the payment page. Later, charge it without the customer present:') }}</p>
<pre dir="ltr"><code>POST {{ $base }}/payment_intents
{ "payment_method_id": "card_...", "amount": 5000, "client_reference_id": "sub-77",
  "return_url": "https://your-app.sd/billing", "metadata": {} }

POST {{ $base }}/payment_intents/pi_.../confirm
→ "status": "succeeded"
→ or "status": "requires_action", "next_action": { "url": "...", "return_url": "..." }</code></pre>
                    <p>{{ __('When the bank asks for authentication, send the customer to next_action.url. They come back to return_url and the intent moves to succeeded.') }}</p>
                </section>

                <section id="subscriptions">
                    <h2>{{ __('Subscriptions') }}</h2>
                    <p>{{ __('Create a plan, then create a checkout session with mode subscription, the plan_id and a customer_id. Products are taken from the plan. After the first payment the card is saved and renewals are charged automatically.') }}</p>
<pre dir="ltr"><code>POST {{ $base }}/checkout/session
{ "client_reference_id": "member-42", "mode": "subscription", "plan_id": "plan_...",
  "customer_id": "cus_...", "success_url": "...", "cancel_url": "...", "metadata": {} }</code></pre>
                    <p>{{ __('A failed renewal is retried after 1, 3 and 5 days, then the subscription is cancelled. When the bank asks for authentication, the invoice.payment_failed event carries payment_intent.next_action.url; send it to your customer.') }}</p>
                    <p dir="ltr" class="font-mono text-sm">subscription.created · subscription.past_due · subscription.cancelled · invoice.paid · invoice.payment_failed</p>
                </section>

                <section id="webhooks">
                    <h2>{{ __('Webhooks') }}</h2>
                    <p>{{ __('Set your endpoint under Developers, Webhooks. We send a POST with a JSON body for these events:') }}</p>
                    <p dir="ltr" class="font-mono text-sm">{{ collect(App\Enums\WebhookEvent::cases())->map->value->implode(' · ') }}</p>
<pre dir="ltr"><code>{ "id": "evt_9fQ2...", "event_type": "payment.succeeded", "created_at": "2026-11-17T10:30:12+02:00", "livemode": false,
  "data": { "payment_id": "2410201030125812", "status": "Successful", "amount": 24000, ... } }</code></pre>
                    <p>{{ __('Each request carries matmoom-timestamp, matmoom-signature and matmoom-event-id headers. The signature is the hex HMAC-SHA256 of the raw body, a dash, and the timestamp, keyed with your signing secret. Reject requests older than five minutes.') }}</p>
                    <p>{{ __('The event id stays the same when we retry or when you resend an event from the dashboard: store it and skip ids you already processed. Use created_at to ignore an event older than the state you already have.') }}</p>
                    <p>{{ __('settlement.paid and settlement.failed arrive on your live endpoint. Live payments are paid out after a :days day hold.', ['days' => config('gateway.settlements.hold_days')]) }}</p>
<pre dir="ltr"><code>$expected = hash_hmac('sha256', $rawBody.'-'.$timestamp, $signingSecret);
$valid = hash_equals($expected, $signatureHeader) && abs(time() - $timestamp) &lt;= 300;</code></pre>
                    <p>{{ __('Answer with any 2xx status. Other answers and timeouts after 10 seconds are retried with growing delays for about a day. Your handler should be idempotent: the same event can arrive twice.') }}</p>
                </section>

                <section id="idempotency">
                    <h2>{{ __('Safe retries') }}</h2>
                    <p>{{ __('Add an Idempotency-Key header to any POST. If the connection drops and you send the same request again with the same key within 24 hours, you get the original response instead of a second session, intent or refund.') }}</p>
<pre dir="ltr"><code>-H "Idempotency-Key: order-1001-create-session"</code></pre>
                </section>

                <section id="testing">
                    <h2>{{ __('Test cards') }}</h2>
                    <p>{{ __('In test mode use any future expiry date and any security code. The verification code is always 1234.') }}</p>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b border-zinc-200 text-zinc-500 "><tr><th class="py-2 pe-4 text-start font-medium">{{ __('Card number') }}</th><th class="py-2 text-start font-medium">{{ __('Result') }}</th></tr></thead>
                            <tbody class="divide-y divide-zinc-200 ">
                                @foreach (['4242 4242 4242 4242' => __('OTP, then approved. Approved without OTP when charged off-session.'), '4000 0000 0000 0002' => __('OTP, then declined.'), '4456 5300 0000 1096' => __('3-D Secure credit card, approved.'), '4456 5300 0000 1104' => __('3-D Secure credit card, declined.'), '5123 4500 0000 0008' => __('Mastercard credit, OTP then approved.')] as $number => $result)
                                    <tr><td class="ltr-nums py-2 pe-4 text-start font-mono whitespace-nowrap">{{ $number }}</td><td class="py-2">{{ $result }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
        </div>
    </body>
</html>
