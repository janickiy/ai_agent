<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTO\Pipeline\ItemAnalysisData;
use App\DTO\Pipeline\ItemDuplicateData;
use App\DTO\Pipeline\PublicationPostData;
use App\DTO\Pipeline\SourceItemData;
use App\Modules\NewsMonitor\Models\NewsCategory;
use App\Modules\NewsMonitor\Models\Source;
use App\Modules\NewsMonitor\Repositories\Pipeline\ItemAnalysisRepository;
use App\Modules\NewsMonitor\Repositories\Pipeline\ItemDuplicateRepository;
use App\Modules\NewsMonitor\Repositories\Pipeline\PublicationPostRepository;
use App\Modules\NewsMonitor\Repositories\Pipeline\SourceItemRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PipelineRepositoryCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_repositories_create_records_from_their_dtos(): void
    {
        $this->seed();
        $source = Source::query()->firstOrFail();
        $category = NewsCategory::query()->firstOrFail();
        $publishedAt = now()->utc()->subHour();
        $sourceItems = app(SourceItemRepository::class);

        $original = $sourceItems->create(SourceItemData::fromArray([
            'source_id' => $source->id,
            'discovery_url' => 'https://repository-crud.example.test/original',
            'canonical_url' => 'https://repository-crud.example.test/original',
            'canonical_url_hash' => hash('sha256', 'repository-crud-original'),
            'discovered_at' => now()->utc(),
        ]));
        $item = $sourceItems->create(SourceItemData::fromArray([
            'source_id' => $source->id,
            'discovery_url' => 'https://repository-crud.example.test/item',
            'canonical_url' => 'https://repository-crud.example.test/item',
            'canonical_url_hash' => hash('sha256', 'repository-crud-item'),
            'discovered_at' => now()->utc(),
        ]));

        $analysis = app(ItemAnalysisRepository::class)->create(new ItemAnalysisData(
            sourceItemId: $item->id,
            categoryId: $category->id,
            isActual: true,
            actualityScore: 0.95,
            isAdvertising: false,
            adConfidence: 0.02,
            categoryConfidence: 0.91,
            hashtags: ['#Строительство'],
            entities: ['Москва'],
            provider: 'rules',
            model: 'deterministic-rules-v1',
            promptVersion: 'test-v1',
            decisionMeta: ['reason' => 'repository_test'],
            checkedAt: now()->utc(),
        ));
        $duplicate = app(ItemDuplicateRepository::class)->create(new ItemDuplicateData(
            sourceItemId: $item->id,
            originalSourceItemId: $original->id,
            method: 'exact_hash',
            similarity: 1.0,
            algorithmVersion: 'test-v1',
            meta: ['reason' => 'repository_test'],
        ));
        $post = app(PublicationPostRepository::class)->create(new PublicationPostData(
            sourceItemId: $original->id,
            uid: $original->canonical_url,
            idempotencyKey: 'repository-crud-post',
            sourceUrl: $original->canonical_url,
            sourceName: $source->name,
            sourcePublishedAt: $publishedAt,
            titleOriginal: 'Тестовая строительная новость',
            descriptionOriginal: 'Описание тестовой строительной новости.',
            fullDescriptionOriginal: 'Полный текст тестовой строительной новости.',
            imageUrl: null,
            imageStorageKey: null,
            readMoreLabel: 'Читать в источнике',
            categoryId: $category->id,
            hashtags: ['#Строительство'],
            contentHash: hash('sha256', 'repository-crud-post-content'),
            status: 'exported',
            validationMeta: ['source' => 'repository_test'],
            readyAt: now()->utc(),
            exportedAt: now()->utc(),
        ));

        self::assertSame($item->id, $analysis->source_item_id);
        self::assertSame($item->id, $duplicate->source_item_id);
        self::assertSame($original->id, $post->source_item_id);
    }
}
