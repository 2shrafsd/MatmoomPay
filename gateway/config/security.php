<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | Ship in report-only mode first, watch the browser console, then enforce
    | by setting SECURITY_CSP_REPORT_ONLY=false.
    |
    */

    'csp_report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', false),

    /*
    |--------------------------------------------------------------------------
    | Platform admin two-factor requirement
    |--------------------------------------------------------------------------
    */

    'admin_requires_two_factor' => (bool) env('SECURITY_ADMIN_REQUIRES_2FA', true),

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Comma separated IPs or CIDR ranges of your load balancer. Needed so rate
    | limits and audit logs see the client IP, and HTTPS is detected correctly.
    | Never use "*" unless the app is only reachable through the proxy.
    |
    */

    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),

];
