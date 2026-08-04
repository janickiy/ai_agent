<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Repositories\Catalog;

use App\DTO\Catalog\NewsCategoryData;
use App\Modules\NewsMonitor\Models\NewsCategory;
use App\Modules\NewsMonitor\Support\NewsTables;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** @extends BaseRepository<NewsCategory, NewsCategoryData> */
final class NewsCategoryRepository extends BaseRepository
{
    public function __construct(NewsCategory $model)
    {
        parent::__construct($model);
    }

    /** @return Builder<NewsCategory> */
    public function forDataTable(): Builder
    {
        return $this->query()->withCount('sources');
    }

    /** @return Collection<int, NewsCategory> */
    public function active(): Collection
    {
        return $this->query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    public function findActiveByCode(string $code): ?NewsCategory
    {
        /** @var NewsCategory|null $category */
        $category = $this->query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        return $category;
    }

    public function isInUse(NewsCategory $category): bool
    {
        return $category->analyses()->exists()
            || $category->publicationPosts()->exists()
            || DB::table(NewsTables::name('subjects'))
                ->where('category_id', $category->getKey())
                ->exists();
    }

    public function withSources(NewsCategory $category): NewsCategory
    {
        return $category->load('sources');
    }

    public function lockForUpdate(NewsCategory $category): NewsCategory
    {
        $this->assertModel($category);

        /** @var NewsCategory $locked */
        $locked = $this->query()
            ->whereKey($category->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $locked;
    }

    protected function modelClass(): string
    {
        return NewsCategory::class;
    }

    /** @return non-empty-list<class-string<NewsCategoryData>> */
    protected function dtoClasses(): array
    {
        return [NewsCategoryData::class];
    }
}
