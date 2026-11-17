<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
<meta name="theme-color" content="#5ce8a6" />
<meta name="color-scheme" content="light" />

<title>
    {{ filled($title ?? null) ? __($title).' · '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

{{-- Light only: pin Flux's appearance so a dark system setting never restyles components. --}}
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">try { localStorage.setItem('flux.appearance', 'light'); } catch (e) {} document.documentElement.classList.remove('dark');</script>

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])

