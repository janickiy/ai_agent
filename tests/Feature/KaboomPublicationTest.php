<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTO\Settings\KaboomSettingsData;
use App\Modules\NewsMonitor\Models\ItemAnalysis;
use App\Modules\NewsMonitor\Models\NewsCategory;
use App\Modules\NewsMonitor\Models\PublicationPost;
use App\Modules\NewsMonitor\Models\Source;
use App\Modules\NewsMonitor\Models\SourceItem;
use App\Modules\NewsMonitor\Publishing\KaboomPublicationException;
use App\Modules\NewsMonitor\Services\KaboomPublisher;
use App\Modules\NewsMonitor\Services\KaboomSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class KaboomPublicationTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'kaboom-secret-for-tests';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.stores.redis', ['driver' => 'array']);
        $this->seed();
        app(KaboomSettings::class)->update(KaboomSettingsData::fromArray([
            'api_key' => self::API_KEY,
            'clear_api_key' => false,
        ]));
    }

    public function test_successful_api_response_creates_exported_post_with_exact_multipart_mapping(): void
    {
        $item = $this->publicationItem();
        Http::fake([
            KaboomSettings::ENDPOINT => Http::response([
                'id' => 701,
                'uid' => $item->canonical_url,
                'created' => true,
                'message' => 'Новость создана',
            ], 201),
        ]);

        $post = app(KaboomPublisher::class)->publish((int) $item->getKey());

        self::assertSame('exported', $post->status);
        self::assertSame($item->canonical_url, $post->uid);
        self::assertSame('Транспортная инфраструктура', $post->category->name);
        self::assertSame(['#Мосты', '#Инфраструктура'], $post->hashtags);
        self::assertNotNull($post->exported_at);
        self::assertSame(701, $post->validation_meta['kaboom']['external_id']);
        self::assertTrue($post->validation_meta['kaboom']['created']);
        self::assertSame('accepted', $item->fresh()->status);
        self::assertNull($item->fresh()->rejection_reason);

        Http::assertSent(function (Request $request) use ($item): bool {
            $fields = [];
            foreach ($request->data() as $part) {
                if (is_array($part) && isset($part['name'], $part['contents'])) {
                    $fields[(string) $part['name']] = (string) $part['contents'];
                }
            }

            return $request->method() === 'POST'
                && $request->url() === KaboomSettings::ENDPOINT
                && $request->isMultipart()
                && $request->hasHeader('X-API-Key', self::API_KEY)
                && $fields === [
                    'uid' => $item->canonical_url,
                    'title' => 'В городе открыли новый мост',
                    'published' => '2026-08-07T08:22:33+00:00',
                    'full_description' => 'Полный текст статьи со всеми существенными подробностями.',
                    'short_description' => 'Краткое описание новости.',
                    'url' => $item->canonical_url,
                    'publication_source' => 'Тестовый источник Kaboom',
                    'category' => 'Транспортная инфраструктура',
                    'hashtags' => '#Мосты,#Инфраструктура',
                    'image_url' => 'https://images.example.test/bridge.jpg',
                ];
        });
        Http::assertSentCount(1);
    }

    public function test_repeat_after_local_success_is_idempotent_and_does_not_call_api_again(): void
    {
        $item = $this->publicationItem();
        Http::fake([
            KaboomSettings::ENDPOINT => Http::response([
                'id' => 702,
                'uid' => $item->canonical_url,
                'created' => false,
                'message' => 'Новость обновлена',
            ], 200),
        ]);

        $first = app(KaboomPublisher::class)->publish((int) $item->getKey());
        $second = app(KaboomPublisher::class)->publish((int) $item->getKey());

        self::assertSame($first->getKey(), $second->getKey());
        self::assertSame(1, PublicationPost::query()->count());
        self::assertFalse($first->validation_meta['kaboom']['created']);
        Http::assertSentCount(1);
    }

    public function test_temporary_api_error_does_not_create_local_publication(): void
    {
        $item = $this->publicationItem();
        Http::fake([
            KaboomSettings::ENDPOINT => Http::response(['message' => 'Временно недоступно'], 503),
        ]);

        try {
            app(KaboomPublisher::class)->publish((int) $item->getKey());
            self::fail('Ожидалось исключение публикации Kaboom.');
        } catch (KaboomPublicationException $exception) {
            self::assertTrue($exception->isRetryable());
        }

        self::assertSame(0, PublicationPost::query()->count());
        self::assertSame(SourceItem::PUBLICATION_QUEUED_REASON, $item->fresh()->rejection_reason);
    }

    public function test_contract_error_does_not_create_local_publication_and_is_not_retried(): void
    {
        $item = $this->publicationItem();
        Http::fake([
            KaboomSettings::ENDPOINT => Http::response(['message' => 'Некорректные данные'], 400),
        ]);

        try {
            app(KaboomPublisher::class)->publish((int) $item->getKey());
            self::fail('Ожидалось исключение публикации Kaboom.');
        } catch (KaboomPublicationException $exception) {
            self::assertFalse($exception->isRetryable());
        }

        self::assertSame(0, PublicationPost::query()->count());
    }

    public function test_same_content_under_another_url_is_not_sent_twice(): void
    {
        $contentHash = hash('sha256', 'shared-full-content');
        $firstItem = $this->publicationItem('first-copy', $contentHash);
        Http::fake([
            KaboomSettings::ENDPOINT => Http::response([
                'id' => 703,
                'uid' => $firstItem->canonical_url,
                'created' => true,
                'message' => 'Новость создана',
            ], 201),
        ]);
        $firstPost = app(KaboomPublisher::class)->publish((int) $firstItem->getKey());

        $secondItem = $this->publicationItem('second-copy', $contentHash);
        $resolvedPost = app(KaboomPublisher::class)->publish((int) $secondItem->getKey());

        self::assertSame($firstPost->getKey(), $resolvedPost->getKey());
        self::assertSame(1, PublicationPost::query()->count());
        self::assertSame('duplicate', $secondItem->fresh()->status);
        self::assertSame('content_hash', $secondItem->fresh()->rejection_reason);
        Http::assertSentCount(1);
    }

    /**
     * Создаёт прошедший анализ материал со всеми полями внешнего контракта Kaboom.
     */
    private function publicationItem(
        string $slug = 'new-bridge',
        ?string $contentHash = null,
    ): SourceItem {
        $category = NewsCategory::query()
            ->where('name', 'Транспортная инфраструктура')
            ->firstOrFail();
        $source = Source::query()->firstOrCreate(
            ['feed_url' => 'https://publisher.example.test/rss'],
            [
                'name' => 'Тестовый источник Kaboom',
                'type' => 'rss',
                'base_url' => 'https://publisher.example.test',
                'domain' => 'publisher.example.test',
                'is_active' => true,
                'is_allowed' => true,
                'poll_interval_minutes' => 30,
                'request_limit' => 30,
                'timeout_seconds' => 20,
                'max_attempts' => 3,
                'status' => 'healthy',
            ],
        );
        $url = "https://publisher.example.test/news/{$slug}";
        $item = SourceItem::query()->create([
            'source_id' => $source->id,
            'discovery_url' => $url,
            'canonical_url' => $url,
            'canonical_url_hash' => hash('sha256', $url),
            'title_original' => 'В городе открыли новый мост',
            'description_original' => 'Краткое описание новости.',
            'body_text' => 'Полный текст статьи со всеми существенными подробностями.',
            'image_url' => 'https://images.example.test/bridge.jpg',
            'source_published_at' => CarbonImmutable::parse('2026-08-07T11:22:33+03:00')->utc(),
            'title_description_hash' => hash('sha256', 'title-description'),
            'content_hash' => $contentHash ?? hash('sha256', 'full-content-'.$slug),
            'status' => 'accepted',
            'rejection_reason' => SourceItem::PUBLICATION_QUEUED_REASON,
            'extraction_meta' => [],
            'discovered_at' => now()->utc(),
            'fetched_at' => now()->utc(),
            'extracted_at' => now()->utc(),
            'analyzed_at' => now()->utc(),
        ]);

        ItemAnalysis::query()->create([
            'source_item_id' => $item->id,
            'category_id' => $category->id,
            'is_actual' => true,
            'actuality_score' => 1,
            'is_advertising' => false,
            'ad_confidence' => 0,
            'category_confidence' => 0.99,
            'hashtags' => ['#Мосты', '#Инфраструктура'],
            'entities' => [],
            'provider' => 'rules',
            'model' => 'rules-v1',
            'prompt_version' => 'test-v1',
            'decision_meta' => [],
            'checked_at' => now()->utc(),
        ]);

        return $item;
    }
}
