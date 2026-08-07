<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessSourceItem;
use App\Models\User;
use App\Modules\NewsMonitor\Models\AuditLog;
use App\Modules\NewsMonitor\Models\ItemAnalysis;
use App\Modules\NewsMonitor\Models\ItemDuplicate;
use App\Modules\NewsMonitor\Models\ProcessingLog;
use App\Modules\NewsMonitor\Models\PublicationPost;
use App\Modules\NewsMonitor\Models\Source;
use App\Modules\NewsMonitor\Models\SourceItem;
use App\Modules\NewsMonitor\Repositories\Pipeline\SourceItemRepository;
use App\Modules\NewsMonitor\Services\NewsPipeline;
use App\Modules\NewsMonitor\Support\NewsTables;
use App\Modules\Admin\Repositories\ContentCleanupRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DataCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_clear_monitoring_content_without_deleting_catalogs_or_audit(): void
    {
        $this->seed();
        $administrator = $this->user('administrator');
        $source = Source::query()->firstOrFail();
        $category = $source->categories()->firstOrFail();
        [$original, $duplicate] = $this->items($source);

        ItemAnalysis::query()->create([
            'source_item_id' => $original->id,
            'category_id' => $category->id,
            'is_actual' => true,
            'actuality_score' => 1,
            'is_advertising' => false,
            'ad_confidence' => 0,
            'category_confidence' => 0.95,
            'hashtags' => ['#Строительство'],
            'entities' => [],
            'provider' => 'rules',
            'model' => 'test-model',
            'prompt_version' => 'test-prompt',
            'decision_meta' => [],
            'checked_at' => now()->utc(),
        ]);
        ItemDuplicate::query()->create([
            'source_item_id' => $duplicate->id,
            'original_source_item_id' => $original->id,
            'method' => 'content_hash',
            'similarity' => 1,
            'algorithm_version' => 'test-v1',
            'meta' => [],
        ]);
        $post = PublicationPost::query()->create([
            'source_item_id' => $original->id,
            'uid' => $original->canonical_url,
            'idempotency_key' => 'cleanup-post',
            'source_url' => $original->canonical_url,
            'source_name' => $source->name,
            'source_published_at' => now()->utc(),
            'title_original' => 'Готовый пост для очистки',
            'description_original' => 'Описание готового поста.',
            'read_more_label' => 'Читать в источнике',
            'category_id' => $category->id,
            'hashtags' => ['#Строительство'],
            'content_hash' => hash('sha256', 'cleanup-post'),
            'status' => 'ready',
            'validation_meta' => [],
            'ready_at' => now()->utc(),
        ]);
        ProcessingLog::query()->create([
            'correlation_id' => (string) Str::uuid(),
            'source_id' => $source->id,
            'source_item_id' => $original->id,
            'publication_post_id' => $post->id,
            'stage' => 'publish',
            'status' => 'success',
            'attempt' => 1,
            'started_at' => now()->utc(),
        ]);
        $eventId = DB::table(NewsTables::name('events'))->insertGetId([
            'fingerprint' => 'cleanup-event',
            'title' => 'Событие для очистки',
            'event_at' => now()->utc(),
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
        DB::table(NewsTables::name('event_items'))->insert([
            'news_event_id' => $eventId,
            'source_item_id' => $original->id,
            'similarity' => 1,
        ]);
        $existingAudit = AuditLog::query()->create([
            'user_id' => $administrator->id,
            'correlation_id' => (string) Str::uuid(),
            'action' => 'existing.audit',
            'entity_type' => 'test',
            'entity_id' => '1',
            'before' => null,
            'after' => null,
            'result' => 'success',
            'created_at' => now()->utc(),
        ]);
        $sourceCount = Source::query()->count();

        $this->actingAs($administrator)
            ->delete(route('admin.data.destroy'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('status', static fn (string $message): bool => str_contains(
                $message,
                'исходных публикаций — 2',
            ));

        foreach ([
            'processing_logs',
            'posts',
            'event_items',
            'duplicates',
            'analyses',
            'source_items',
            'events',
        ] as $table) {
            $this->assertDatabaseCount(NewsTables::name($table), 0);
        }

        self::assertSame($sourceCount, Source::query()->count());
        self::assertTrue(AuditLog::query()->whereKey($existingAudit->id)->exists());
        $purgeAudit = AuditLog::query()->where('action', 'content.purged')->sole();
        self::assertSame([
            'source_items' => 2,
            'posts' => 1,
            'processing_logs' => 1,
        ], $purgeAudit->before);

        (new ProcessSourceItem($original->id))->handle(
            app(NewsPipeline::class),
            app(SourceItemRepository::class),
        );
    }

    public function test_content_cleanup_repository_purges_only_monitoring_content_and_returns_pre_purge_counts(): void
    {
        $this->seed();
        $source = Source::query()->firstOrFail();
        $category = $source->categories()->firstOrFail();
        [$original, $duplicate] = $this->items($source);

        ItemAnalysis::query()->create([
            'source_item_id' => $original->id,
            'category_id' => $category->id,
            'is_actual' => true,
            'actuality_score' => 1,
            'is_advertising' => false,
            'ad_confidence' => 0,
            'category_confidence' => 0.95,
            'hashtags' => [],
            'entities' => [],
            'provider' => 'rules',
            'model' => 'test-model',
            'prompt_version' => 'test-prompt',
            'decision_meta' => [],
            'checked_at' => now()->utc(),
        ]);
        ItemDuplicate::query()->create([
            'source_item_id' => $duplicate->id,
            'original_source_item_id' => $original->id,
            'method' => 'content_hash',
            'similarity' => 1,
            'algorithm_version' => 'test-v1',
            'meta' => [],
        ]);
        $post = PublicationPost::query()->create([
            'source_item_id' => $original->id,
            'uid' => $original->canonical_url,
            'idempotency_key' => 'repository-cleanup-post',
            'source_url' => $original->canonical_url,
            'source_name' => $source->name,
            'source_published_at' => now()->utc(),
            'title_original' => 'Пост для репозиторной очистки',
            'description_original' => 'Описание поста для репозиторной очистки',
            'read_more_label' => 'Читать в источнике',
            'category_id' => $category->id,
            'hashtags' => ['#Тест'],
            'content_hash' => hash('sha256', 'repository-cleanup-post'),
            'status' => 'ready',
            'validation_meta' => [],
            'ready_at' => now()->utc(),
        ]);
        ProcessingLog::query()->create([
            'correlation_id' => (string) Str::uuid(),
            'source_id' => $source->id,
            'source_item_id' => $original->id,
            'publication_post_id' => $post->id,
            'stage' => 'publish',
            'status' => 'success',
            'attempt' => 1,
            'started_at' => now()->utc(),
        ]);
        $eventId = DB::table(NewsTables::name('events'))->insertGetId([
            'fingerprint' => 'repository-cleanup-event',
            'title' => 'Событие для репозиторной очистки',
            'event_at' => now()->utc(),
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
        DB::table(NewsTables::name('event_items'))->insert([
            'news_event_id' => $eventId,
            'source_item_id' => $original->id,
            'similarity' => 1,
        ]);

        $result = app(ContentCleanupRepository::class)->purge();

        self::assertSame([
            'source_items' => 2,
            'posts' => 1,
            'processing_logs' => 1,
        ], $result);
        foreach ([
            'processing_logs',
            'posts',
            'event_items',
            'duplicates',
            'analyses',
            'source_items',
            'events',
        ] as $table) {
            $this->assertDatabaseCount(NewsTables::name($table), 0);
        }

        self::assertTrue(Source::query()->whereKey($source->id)->exists());
        self::assertTrue($source->categories()->whereKey($category->id)->exists());
    }

    public function test_viewer_cannot_see_or_call_clear_action(): void
    {
        $this->seed();
        $viewer = $this->user('viewer');
        $source = Source::query()->firstOrFail();
        [$item] = $this->items($source);

        $this->actingAs($viewer)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Очистить всё');

        $this->actingAs($viewer)
            ->delete(route('admin.data.destroy'))
            ->assertForbidden();

        self::assertTrue(SourceItem::query()->whereKey($item->id)->exists());
    }

    public function test_administrator_sees_clear_action_and_empty_cleanup_is_idempotent(): void
    {
        $this->seed();
        $administrator = $this->user('administrator');

        $this->actingAs($administrator)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Очистить всё')
            ->assertSee(route('admin.data.destroy'), false);

        $this->actingAs($administrator)
            ->delete(route('admin.data.destroy'))
            ->assertRedirect(route('admin.dashboard'));
        $this->actingAs($administrator)
            ->delete(route('admin.data.destroy'))
            ->assertRedirect(route('admin.dashboard'));

        self::assertSame(2, AuditLog::query()->where('action', 'content.purged')->count());
    }

    /** @return array{SourceItem, SourceItem} */
    private function items(Source $source): array
    {
        $originalUrl = 'https://example.test/cleanup/original';
        $duplicateUrl = 'https://example.test/cleanup/duplicate';
        $original = SourceItem::query()->create([
            'source_id' => $source->id,
            'discovery_url' => $originalUrl,
            'canonical_url' => $originalUrl,
            'canonical_url_hash' => hash('sha256', $originalUrl),
            'title_original' => 'Исходная публикация',
            'status' => 'accepted',
            'discovered_at' => now()->utc(),
            'source_published_at' => now()->utc(),
        ]);
        $duplicate = SourceItem::query()->create([
            'source_id' => $source->id,
            'discovery_url' => $duplicateUrl,
            'canonical_url' => $duplicateUrl,
            'canonical_url_hash' => hash('sha256', $duplicateUrl),
            'title_original' => 'Дубликат публикации',
            'status' => 'duplicate',
            'discovered_at' => now()->utc(),
            'source_published_at' => now()->utc(),
        ]);

        return [$original, $duplicate];
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'admin_access' => true,
        ]);
    }
}
