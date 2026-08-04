<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTO\Catalog\SourceData;
use App\Jobs\ProcessSourceItem;
use App\Modules\NewsMonitor\Contracts\HttpFetcher;
use App\Modules\NewsMonitor\DTO\FetchResult;
use App\Modules\NewsMonitor\Models\SourceItem;
use App\Modules\NewsMonitor\Repositories\Catalog\SourceRepository;
use App\Modules\NewsMonitor\Services\SourceMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

final class SourceMonitorRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitor_creates_discovered_item_and_updates_source_through_repositories(): void
    {
        $feedUrl = 'https://repository-monitor.example.test/feed.xml';
        $articleUrl = 'https://repository-monitor.example.test/news/1?utm_source=test';
        $source = app(SourceRepository::class)->create(SourceData::fromArray([
            'name' => 'Repository Monitor',
            'domain' => 'repository-monitor.example.test',
            'type' => 'rss',
            'source_class' => 'industry_media',
            'trust_score' => 80,
            'base_url' => 'https://repository-monitor.example.test',
            'feed_url' => $feedUrl,
            'is_active' => true,
            'is_trusted' => false,
            'is_allowed' => true,
            'poll_interval_minutes' => 30,
            'request_limit' => 20,
            'timeout_seconds' => 10,
            'max_attempts' => 3,
            'category_ids' => [],
        ]));

        $fetcher = Mockery::mock(HttpFetcher::class);
        $fetcher->shouldReceive('get')
            ->once()
            ->with($feedUrl)
            ->andReturn(new FetchResult(
                url: $feedUrl,
                body: "<?xml version=\"1.0\"?><rss><channel><item><link>{$articleUrl}</link><title>Новость</title></item></channel></rss>",
                headers: ['Content-Type' => 'application/rss+xml'],
                status: 200,
            ));
        $this->app->instance(HttpFetcher::class, $fetcher);
        Queue::fake([ProcessSourceItem::class]);

        self::assertSame(
            ['sources' => 1, 'discovered' => 1, 'failed' => 0],
            app(SourceMonitor::class)->monitor((int) $source->getKey(), force: true),
        );

        $item = SourceItem::query()->sole();
        self::assertSame('https://repository-monitor.example.test/news/1', $item->canonical_url);
        self::assertSame('discovered', $item->status);
        self::assertSame('healthy', $source->fresh()->status);
        self::assertNotNull($source->fresh()->last_success_at);
        Queue::assertPushed(
            ProcessSourceItem::class,
            static fn (ProcessSourceItem $job): bool => $job->sourceItemId === $item->getKey(),
        );
    }
}
