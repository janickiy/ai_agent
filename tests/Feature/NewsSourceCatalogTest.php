<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\NewsMonitor\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NewsSourceCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_screenshot_source_catalog_is_seeded_idempotently(): void
    {
        $this->seed();
        Source::query()->where('domain', 'minstroyrf.gov.ru')->update([
            'feed_url' => 'https://minstroyrf.gov.ru/rss',
            'status' => 'healthy',
        ]);
        $this->seed();

        self::assertSame(30, Source::query()->count());
        self::assertSame(
            [
                'name' => 'Минстрой России',
                'source_class' => 'official_federal',
                'trust_score' => 100,
                'poll_interval_minutes' => 30,
                'status' => 'healthy',
            ],
            Source::query()
                ->where('domain', 'minstroyrf.gov.ru')
                ->firstOrFail()
                ->only(['name', 'source_class', 'trust_score', 'poll_interval_minutes', 'status']),
        );
        self::assertSame(
            'https://minstroyrf.gov.ru/rss',
            Source::query()->where('domain', 'minstroyrf.gov.ru')->value('feed_url'),
        );
    }
}
