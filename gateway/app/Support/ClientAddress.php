<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Rate-limit key for the client's network. One IPv6 customer usually holds a whole /64, so IPv6 addresses are
 * grouped by that prefix; otherwise rotating addresses inside it would escape every per-address limit.
 */
final class ClientAddress
{
    public static function key(Request $request): string
    {
        $ip = (string) $request->ip();

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return $ip;
        }

        $packed = inet_pton($ip);

        if ($packed === false) {
            return $ip;
        }

        return bin2hex(substr($packed, 0, 8)).'::/64';
    }
}
