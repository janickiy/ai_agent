<?php

declare(strict_types=1);

namespace App\NewsMonitor\Services;

use App\NewsMonitor\Exceptions\UnsafeUrlException;

final class UrlCanonicalizer
{
    private const TRACKING_PARAMETERS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'yclid', 'gclid', 'fbclid', 'from', 'ref',
    ];

    public function canonicalize(string $url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new UnsafeUrlException('URL must be absolute.');
        }

        $scheme = mb_strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new UnsafeUrlException('Only HTTP and HTTPS URLs are supported.');
        }

        $host = mb_strtolower(rtrim((string) $parts['host'], '.'));
        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $portPart = $port !== null && ! (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))
            ? ":{$port}"
            : '';
        $path = $parts['path'] ?? '/';
        $path = $path === '' ? '/' : preg_replace('#/{2,}#', '/', $path);
        $path = $path !== '/' ? rtrim((string) $path, '/') : '/';

        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        foreach (self::TRACKING_PARAMETERS as $parameter) {
            unset($query[$parameter]);
        }
        ksort($query);
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return "{$scheme}://{$host}{$portPart}{$path}".($queryString === '' ? '' : "?{$queryString}");
    }
}
