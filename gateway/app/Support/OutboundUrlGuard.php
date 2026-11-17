<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\UnsafeOutboundUrl;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Protects outbound requests to merchant-supplied URLs (webhooks) from SSRF: only http(s), no
 * credentials, and every resolved address must be public. The resolved IP is pinned for the request
 * so a DNS change between the check and the call cannot redirect it.
 */
final class OutboundUrlGuard
{
    /**
     * Returns a validation message, or null when the URL may be stored.
     */
    public static function storageError(string $url, bool $livemode): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return __('Enter a full URL, starting with https://');
        }

        $scheme = strtolower($parts['scheme']);

        if ($scheme !== 'https' && ($livemode || $scheme !== 'http')) {
            return $livemode
                ? __('Live webhooks must use https.')
                : __('The URL must start with http:// or https://');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return __('The URL must not contain a username or password.');
        }

        return null;
    }

    /**
     * @return array{host: string, port: int, ip: string}
     *
     * @throws UnsafeOutboundUrl
     */
    public static function resolve(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new UnsafeOutboundUrl('Unsupported URL.');
        }

        $host = trim($parts['host'], '[]');
        $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::lookup($host);

        if ($addresses === []) {
            throw new UnsafeOutboundUrl('The host could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (! config('gateway.webhooks.allow_private_hosts') && ! self::isPublic($address)) {
                throw new UnsafeOutboundUrl('The host resolves to a private or reserved address.');
            }
        }

        return ['host' => $host, 'port' => (int) $port, 'ip' => $addresses[0]];
    }

    /**
     * Prefixes that tunnel to an IPv4 address we cannot see (NAT64, 6to4, Teredo, IPv4-mapped).
     *
     * @var list<string>
     */
    private const TUNNELLED_IPV6 = ['64:ff9b::/96', '64:ff9b:1::/48', '2002::/16', '2001::/32', '::ffff:0:0/96'];

    /**
     * Only globally routable addresses: this also rejects carrier-grade NAT (100.64.0.0/10, home of some cloud
     * metadata services), benchmarking ranges and IPv6 prefixes that smuggle a private IPv4 target.
     */
    public static function isPublic(string $ip): bool
    {
        // @phpstan-ignore identical.alwaysFalse (PHPStan's stub misses that FILTER_FLAG_GLOBAL_RANGE rejects addresses)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
            return false;
        }

        return ! str_contains($ip, ':') || ! IpUtils::checkIp($ip, self::TUNNELLED_IPV6);
    }

    /**
     * @return list<string>
     */
    private static function lookup(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $addresses = [];

        foreach ($records as $record) {
            $addresses[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }

        return array_values(array_filter($addresses));
    }
}
