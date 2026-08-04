<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\NewsMonitor\Services\SafeHttpClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SafeHttpClientTest extends TestCase
{
    public function test_it_preserves_a_trailing_slash_received_in_a_redirect(): void
    {
        self::assertSame('sqlite', config('database.default'));
        self::assertSame(':memory:', config('database.connections.sqlite.database'));

        Cache::flush();
        Http::fake([
            'https://1.1.1.1/robots.txt' => Http::response('', 200),
            'https://1.1.1.1/feed' => Http::response('', 301, ['Location' => '/feed/']),
            'https://1.1.1.1/feed/' => Http::response('<rss version="2.0"><channel/></rss>', 200),
        ]);

        $result = app(SafeHttpClient::class)->get('https://1.1.1.1/feed');

        self::assertSame('https://1.1.1.1/feed/', $result->url);
        self::assertSame(200, $result->status);
        Http::assertSentCount(3);
    }

    public function test_it_converts_an_internationalized_host_before_requesting_it(): void
    {
        Cache::flush();
        Http::fake([
            'https://xn--d1aqf.xn--p1ai/robots.txt' => Http::response('', 200),
            'https://xn--d1aqf.xn--p1ai/news' => Http::response('<html lang="ru"></html>', 200),
        ]);

        $result = app(SafeHttpClient::class)->get('https://дом.рф/news');

        self::assertSame('https://xn--d1aqf.xn--p1ai/news', $result->url);
        self::assertSame(200, $result->status);
        Http::assertSentCount(2);
    }
}
