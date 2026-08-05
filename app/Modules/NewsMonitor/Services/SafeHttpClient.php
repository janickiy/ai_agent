<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Services;

use App\Modules\NewsMonitor\Contracts\HttpFetcher;
use App\Modules\NewsMonitor\DTO\FetchResult;
use App\Modules\NewsMonitor\Exceptions\UnsafeUrlException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Безопасно загружает внешние ленты и страницы новостей по HTTP.
 *
 * Клиент защищает конвейер от SSRF, проверяет robots.txt, контролирует редиректы
 * и размер ответа, а также возвращает унифицированный DTO результата загрузки.
 */
final class SafeHttpClient implements HttpFetcher
{
    public function __construct(private readonly UrlCanonicalizer $canonicalizer) {}


    /**
     * Загружает внешний URL с ручной безопасной обработкой каждого редиректа.
     *
     * Перед запросом проверяются robots.txt и публичность адреса, после ответа — HTTP-статус
     * и допустимый размер содержимого; итоговые URL, тело, заголовки и статус возвращаются в DT
     *
     * @param string $url
     * @param bool $checkRobots
     * @return FetchResult
     * @throws \Illuminate\Http\Client\ConnectionException
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function get(string $url, bool $checkRobots = true): FetchResult
    {
        $current = $this->normalizeFetchUrl($url);
        if ($checkRobots && ! $this->robotsAllows($current)) {
            throw new UnsafeUrlException('URL is disallowed by robots.txt.');
        }

        $maxRedirects = (int) config('news.max_redirects');
        for ($redirect = 0; $redirect <= $maxRedirects; $redirect++) {
            $this->assertPublicUrl($current);
            $response = Http::withUserAgent('ConstructionNewsMonitor/1.0 (+'.config('app.url').')')
                ->accept('*/*')
                ->connectTimeout(min(10, (int) config('news.fetch_timeout_seconds')))
                ->timeout((int) config('news.fetch_timeout_seconds'))
                ->withOptions(['allow_redirects' => false])
                ->get($current);

            if ($response->redirect()) {
                if ($redirect === $maxRedirects) {
                    throw new RuntimeException('Maximum redirect count exceeded.');
                }
                $location = $response->header('Location');
                if (! is_string($location) || $location === '') {
                    throw new RuntimeException('Redirect has no Location header.');
                }
                $current = $this->normalizeFetchUrl(
                    (string) UriResolver::resolve(new Uri($current), new Uri($location)),
                );

                continue;
            }

            $response->throw();
            $this->assertResponseSize($response);
            $headers = [];
            foreach ($response->headers() as $name => $values) {
                $headers[$name] = implode(', ', $values);
            }

            return new FetchResult($current, $response->body(), $headers, $response->status());
        }

        throw new RuntimeException('Unexpected redirect state.');
    }

    /**
     * Канонизирует URL для загрузки, преобразует международный домен в ASCII
     * и сохраняет значимый завершающий слеш исходного пути.
     *
     * @param string $url
     * @return string
     */
    private function normalizeFetchUrl(string $url): string
    {
        $decoded = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $normalized = $this->canonicalizer->canonicalize($decoded);
        $parts = parse_url($normalized);
        $host = (string) ($parts['host'] ?? '');
        if ($host !== '' && preg_match('/[^\x20-\x7E]/', $host) === 1) {
            $asciiHost = function_exists('idn_to_ascii')
                ? idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46)
                : false;
            if (! is_string($asciiHost) || $asciiHost === '') {
                throw new UnsafeUrlException('Internationalized host cannot be converted to ASCII.');
            }

            $origin = $parts['scheme'].'://'.$host;
            $normalized = $parts['scheme'].'://'.$asciiHost.substr($normalized, strlen($origin));
        }

        $path = (string) (parse_url($decoded, PHP_URL_PATH) ?? '/');
        if ($path === '/' || ! str_ends_with($path, '/')) {
            return $normalized;
        }

        $queryPosition = strpos($normalized, '?');
        if ($queryPosition === false) {
            return "{$normalized}/";
        }

        return substr($normalized, 0, $queryPosition).'/'.substr($normalized, $queryPosition);
    }

    /**
     * Проверяет, что URL не указывает на localhost, приватный, зарезервированный
     * или link-local IP-адрес, предотвращая SSRF-доступ к внутренней инфраструктуре.
     */
    public function assertPublicUrl(string $url): void
    {
        $parts = parse_url($this->canonicalizer->canonicalize($url));
        $host = (string) ($parts['host'] ?? '');
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new UnsafeUrlException('Local hosts are forbidden.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : $this->resolveHost($host);
        if ($ips === []) {
            throw new UnsafeUrlException('Host cannot be resolved.');
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new UnsafeUrlException('Private, reserved and link-local addresses are forbidden.');
            }
        }
    }

    /**
     * Разрешает доменное имя в уникальный список IPv4- и IPv6-адресов для SSRF-проверки.
     *
     * @param string $host
     * @return array
     */
    private function resolveHost(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        $ips = [];
        foreach ($records ?: [] as $record) {
            if (isset($record['ip'])) {
                $ips[] = $record['ip'];
            }
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * Проверяет заявленный и фактический размер HTTP-ответа относительно системного лимита.
     * Двойная проверка защищает память даже при отсутствии или неверном `Content-Length`.
     *
     * @param Response $response
     * @return void
     */
    private function assertResponseSize(Response $response): void
    {
        $maximum = (int) config('news.max_response_bytes');
        $contentLength = (int) ($response->header('Content-Length') ?: 0);
        if ($contentLength > $maximum || strlen($response->body()) > $maximum) {
            throw new RuntimeException('Source response exceeds the configured size limit.');
        }
    }

    /**
     * Проверяет путь URL по правилам `robots.txt`, кешируя правила для каждого origin на час.
     *
     * Если robots.txt недоступен, загрузка разрешается, чтобы временная ошибка служебного файла
     * не блокировала весь источник.
     *
     * @param string $url
     * @return bool
     */
    private function robotsAllows(string $url): bool
    {
        $parts = parse_url($url);
        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $path = $parts['path'] ?? '/';

        $rules = Cache::remember('robots:'.hash('sha256', $origin), now()->addHour(), function () use ($origin): array {
            try {
                $result = $this->get($origin.'/robots.txt', false);
            } catch (\Throwable) {
                return [];
            }

            $active = false;
            $disallow = [];
            foreach (preg_split('/\R/u', $result->body) ?: [] as $line) {
                $line = trim((string) preg_replace('/#.*/', '', $line));
                if (preg_match('/^User-agent:\s*(.+)$/i', $line, $match)) {
                    $active = trim($match[1]) === '*';
                } elseif ($active && preg_match('/^Disallow:\s*(.*)$/i', $line, $match) && trim($match[1]) !== '') {
                    $disallow[] = trim($match[1]);
                }
            }

            return $disallow;
        });

        foreach ($rules as $blockedPath) {
            if ($blockedPath === '/' || str_starts_with($path, $blockedPath)) {
                return false;
            }
        }

        return true;
    }
}
