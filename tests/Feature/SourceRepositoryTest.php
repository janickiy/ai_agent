<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTO\Catalog\NewsCategoryData;
use App\DTO\Catalog\SourceData;
use App\DTO\Catalog\SourceStatusData;
use App\Modules\NewsMonitor\Models\NewsCategory;
use App\Modules\NewsMonitor\Models\Source;
use App\Modules\NewsMonitor\Repositories\Catalog\SourceRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class SourceRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_creates_and_updates_source_with_category_sync(): void
    {
        $this->seed();
        $categories = NewsCategory::query()->orderBy('id')->limit(3)->get();
        self::assertCount(3, $categories);

        $repository = app(SourceRepository::class);
        $source = $repository->create($this->sourceData([
            'category_ids' => [
                $categories[0]->getKey(),
                $categories[1]->getKey(),
                $categories[0]->getKey(),
            ],
        ]));

        self::assertInstanceOf(Source::class, $source);
        self::assertSame('repository.example.test', $source->domain);
        self::assertSame(
            [$categories[0]->getKey(), $categories[1]->getKey()],
            $this->categoryIds($source),
        );

        $source = $repository->update($source, $this->sourceData([
            'name' => 'Обновлённый источник',
            'trust_score' => 95,
            'category_ids' => [$categories[2]->getKey()],
        ]));

        self::assertSame('Обновлённый источник', $source->name);
        self::assertSame(95, $source->trust_score);
        self::assertSame([$categories[2]->getKey()], $this->categoryIds($source));

        $source = $repository->update($source, SourceStatusData::fromArray([
            'is_active' => false,
            'status' => 'error',
            'last_error' => 'Временная ошибка подключения',
        ]));

        self::assertFalse($source->is_active);
        self::assertSame('error', $source->status);
        self::assertSame('Временная ошибка подключения', $source->last_error);
        self::assertSame([$categories[2]->getKey()], $this->categoryIds($source));
    }

    public function test_repository_rejects_dto_for_another_entity(): void
    {
        $repository = app(SourceRepository::class);
        $wrongDto = NewsCategoryData::fromArray([
            'name' => 'Не источник',
            'code' => 'not_a_source',
            'hashtag' => '#НеИсточник',
            'keywords' => ['тест'],
            'is_active' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SourceRepository::create expects');

        $repository->create($wrongDto);
    }

    /** @param array<string, mixed> $overrides */
    private function sourceData(array $overrides = []): SourceData
    {
        return SourceData::fromArray(array_replace([
            'name' => 'Источник репозитория',
            'domain' => 'repository.example.test',
            'type' => 'rss',
            'source_class' => 'industry_media',
            'trust_score' => 80,
            'base_url' => 'https://repository.example.test',
            'feed_url' => 'https://repository.example.test/feed.xml',
            'is_active' => true,
            'is_trusted' => false,
            'is_allowed' => true,
            'poll_interval_minutes' => 30,
            'request_limit' => 20,
            'timeout_seconds' => 10,
            'max_attempts' => 3,
            'category_ids' => [],
        ], $overrides));
    }

    /** @return list<int> */
    private function categoryIds(Source $source): array
    {
        return $source->fresh()
            ->categories()
            ->pluck(NewsCategory::query()->getModel()->qualifyColumn('id'))
            ->map(static fn (mixed $id): int => (int) $id)
            ->sort()
            ->values()
            ->all();
    }
}
