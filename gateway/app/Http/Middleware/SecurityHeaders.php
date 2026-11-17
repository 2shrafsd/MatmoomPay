<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers and a nonce-based Content-Security-Policy for every web response.
 * The hosted payment pages cannot be framed, which blocks clickjacking of the card form.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        /** @var Response $response */
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(self)',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        $cspHeader = config('security.csp_report_only') ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
        $headers[$cspHeader] = $this->contentSecurityPolicy();

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        $response->headers->remove('X-Powered-By');

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $nonce = Vite::cspNonce();
        $devServer = app()->isLocal() && Vite::isRunningHot() ? $this->devServerSources() : '';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic' 'unsafe-eval'".$devServer,
            "style-src 'self' 'unsafe-inline'".$devServer,
            "img-src 'self' data: blob:",
            "font-src 'self' data:".$devServer,
            "connect-src 'self'".$devServer,
            "frame-ancestors 'none'",
            "base-uri 'self'",
            // Merchants' success and cancel URLs are https in production; local shops may run on http.
            app()->isProduction() ? "form-action 'self' https:" : "form-action 'self' https: http:",
            "object-src 'none'",
        ]);
    }

    /**
     * Sources for the local Vite dev server: localhost, plus whatever host `npm run dev` wrote to the hot
     * file (for example https://matmoompay.test:5173 under Herd), with its websocket for hot reload.
     */
    private function devServerSources(): string
    {
        $sources = ['http://localhost:*', 'http://127.0.0.1:*', 'ws://localhost:*', 'ws://127.0.0.1:*'];
        $hot = trim((string) @file_get_contents(Vite::hotFile()));
        $scheme = parse_url($hot, PHP_URL_SCHEME);
        $host = parse_url($hot, PHP_URL_HOST);

        if (in_array($scheme, ['http', 'https'], true) && is_string($host) && preg_match('/^[a-z0-9.-]+$/i', $host) === 1) {
            $host = strtolower($host);
            $sources[] = "{$scheme}://{$host}:*";
            $sources[] = ($scheme === 'https' ? 'wss' : 'ws')."://{$host}:*";
        }

        return ' '.implode(' ', array_unique($sources));
    }
}
