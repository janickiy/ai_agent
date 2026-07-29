<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\NewsMonitor\AI\Contracts\AIProvider;
use App\NewsMonitor\AI\Providers\RuleBasedAIProvider;
use App\NewsMonitor\Contracts\HttpFetcher;
use App\NewsMonitor\DTO\FetchResult;
use App\NewsMonitor\Models\PublicationPost;
use App\NewsMonitor\Models\Source;
use App\NewsMonitor\Models\SourceItem;
use App\NewsMonitor\Services\AgentSettings;
use App\NewsMonitor\Services\NewsPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class NewsPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_exactly_one_ready_post_and_is_idempotent(): void
    {
        $this->seed();
        $item = $this->item('https://example.org/news/bridge');
        $this->fakeFetch([
            'https://example.org/news/bridge' => $this->html(
                'https://example.org/news/bridge',
                'В регионе завершено строительство нового моста',
                'Новый мост открыл движение по городской магистрали.',
            ),
        ]);

        $pipeline = app(NewsPipeline::class);
        $first = $pipeline->process($item);
        $second = $pipeline->process($item->fresh());

        self::assertNotNull($first);
        self::assertSame($first->id, $second?->id);
        self::assertSame(1, PublicationPost::query()->count());
        self::assertSame('ready', $first->status);
        self::assertSame('Читать в источнике', $first->read_more_label);
        self::assertSame('В регионе завершено строительство нового моста', $first->title_original);
        self::assertSame('Новый мост открыл движение по городской магистрали.', $first->description_original);
        self::assertSame('accepted', $item->fresh()->status);
    }

    public function test_same_content_at_another_url_is_rejected_as_duplicate(): void
    {
        $this->seed();
        $first = $this->item('https://example.org/news/one');
        $second = $this->item('https://another.example/news/two');
        $title = 'Завершено строительство нового моста';
        $description = 'Новый мост и магистраль открыты для движения.';
        $this->fakeFetch([
            $first->canonical_url => $this->html($first->canonical_url, $title, $description),
            $second->canonical_url => $this->html($second->canonical_url, $title, $description),
        ]);

        $pipeline = app(NewsPipeline::class);
        self::assertNotNull($pipeline->process($first));
        self::assertNull($pipeline->process($second));

        self::assertSame(1, PublicationPost::query()->count());
        self::assertSame('duplicate', $second->fresh()->status);
        self::assertSame('content_hash', $second->fresh()->rejection_reason);
    }

    public function test_advertising_and_missing_description_do_not_create_posts(): void
    {
        $this->seed();
        $advertising = $this->item('https://example.org/news/ad');
        $invalid = $this->item('https://example.org/news/invalid');
        $this->fakeFetch([
            $advertising->canonical_url => $this->html(
                $advertising->canonical_url,
                'Строительство жилого комплекса',
                'На правах рекламы. Купите квартиру со скидкой.',
            ),
            $invalid->canonical_url => '<html><head><title>Строительство объекта</title><time datetime="'.now()->utc()->toIso8601String().'"></time></head><body><div>Текст</div></body></html>',
        ]);

        $pipeline = app(NewsPipeline::class);
        self::assertNull($pipeline->process($advertising));
        self::assertNull($pipeline->process($invalid));

        self::assertSame('rejected_advertising', $advertising->fresh()->status);
        self::assertSame('validation_failed', $invalid->fresh()->status);
        self::assertSame(0, PublicationPost::query()->count());
    }

    public function test_old_publication_is_rejected_by_actuality_window(): void
    {
        $this->seed();
        $item = $this->item('https://example.org/news/old');
        $date = now()->utc()->subDays(30)->toIso8601String();
        $html = '<html><head><meta property="og:title" content="Строительство моста">'
            .'<meta name="description" content="Подрядчик завершил строительство">'
            .'<meta property="article:published_time" content="'.$date.'"></head>'
            .'<body><article><p>Подрядчик завершил строительство моста.</p></article></body></html>';
        $this->fakeFetch([$item->canonical_url => $html]);

        self::assertNull(app(NewsPipeline::class)->process($item));
        self::assertSame('rejected_irrelevant', $item->fresh()->status);
        self::assertSame('outside_actuality_window', $item->fresh()->rejection_reason);
    }

    public function test_disabled_automatic_publication_keeps_analyzed_material_without_a_post(): void
    {
        $this->seed();
        app(AgentSettings::class)->update([
            'collection_enabled' => true,
            'automatic_publication' => false,
            'max_news_age_hours' => 72,
            'event_similarity_threshold' => 0.72,
        ]);
        $item = $this->item('https://example.org/news/settings');
        $this->fakeFetch([
            $item->canonical_url => $this->html(
                $item->canonical_url,
                'В регионе построен новый мост',
                'Подрядчик завершил строительство транспортного объекта.',
            ),
        ]);

        self::assertNull(app(NewsPipeline::class)->process($item));
        self::assertSame('analyzed', $item->fresh()->status);
        self::assertSame('publication_output_disabled', $item->fresh()->rejection_reason);
        self::assertSame(0, PublicationPost::query()->count());
    }

    private function item(string $url): SourceItem
    {
        $source = Source::query()->firstOrCreate(
            ['domain' => parse_url($url, PHP_URL_HOST), 'feed_url' => 'https://'.parse_url($url, PHP_URL_HOST).'/rss'],
            [
                'name' => 'Тестовый источник',
                'type' => 'rss',
                'base_url' => 'https://'.parse_url($url, PHP_URL_HOST),
                'is_active' => true,
                'is_allowed' => true,
                'poll_interval_minutes' => 30,
                'request_limit' => 30,
                'timeout_seconds' => 20,
                'max_attempts' => 3,
                'status' => 'healthy',
            ],
        );

        return SourceItem::query()->create([
            'source_id' => $source->id,
            'discovery_url' => $url,
            'canonical_url' => $url,
            'canonical_url_hash' => hash('sha256', $url),
            'status' => 'discovered',
            'extraction_meta' => [],
            'discovered_at' => now()->utc(),
        ]);
    }

    /** @param array<string, string> $pages */
    private function fakeFetch(array $pages): void
    {
        $fetcher = Mockery::mock(HttpFetcher::class);
        $fetcher->shouldReceive('get')->andReturnUsing(
            static fn (string $url): FetchResult => new FetchResult($url, $pages[$url], ['Content-Type' => 'text/html'], 200),
        );
        $fetcher->shouldReceive('assertPublicUrl')->zeroOrMoreTimes();
        $this->app->instance(HttpFetcher::class, $fetcher);
        $this->app->instance(AIProvider::class, new RuleBasedAIProvider);
    }

    private function html(string $url, string $title, string $description): string
    {
        $date = now()->utc()->subHour()->toIso8601String();

        return <<<HTML
            <html><head>
              <link rel="canonical" href="{$url}">
              <meta property="og:title" content="{$title}">
              <meta name="description" content="{$description}">
              <meta property="article:published_time" content="{$date}">
            </head><body><article><p>{$description}</p><p>Подрядчик завершил строительство объекта.</p></article></body></html>
            HTML;
    }
}
