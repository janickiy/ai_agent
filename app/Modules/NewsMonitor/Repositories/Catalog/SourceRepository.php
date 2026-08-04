<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Repositories\Catalog;

use App\DTO\Catalog\SourceData;
use App\DTO\Catalog\SourceStatusData;
use App\DTO\DataTransferObject;
use App\Modules\NewsMonitor\Models\Source;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;

/** @extends BaseRepository<Source, SourceData|SourceStatusData> */
final class SourceRepository extends BaseRepository
{
    public function __construct(Source $model)
    {
        parent::__construct($model);
    }

    /** @return Builder<Source> */
    public function forDataTable(): Builder
    {
        return $this->query()->withCount('items');
    }

    /**
     * @param int|null $sourceId
     * @param bool $force
     * @return LazyCollection
     */
    public function monitorable(?int $sourceId = null, bool $force = false): LazyCollection
    {
        return $this->query()
            ->where('is_active', true)
            ->where('is_allowed', true)
            ->whereNotNull('feed_url')
            ->when(
                $sourceId !== null,
                static fn (Builder $query) => $query->whereKey($sourceId),
            )
            ->when(
                ! $force,
                static fn (Builder $query) => $query->where(
                    static fn (Builder $due) => $due
                        ->whereNull('next_poll_at')
                        ->orWhere('next_poll_at', '<=', now()->utc()),
                ),
            )
            ->cursor();
    }

    /**
     * @param Source $source
     * @return Source
     */
    public function withCategories(Source $source): Source
    {
        return $source->load('categories');
    }

    /**
     * @param Source $source
     * @return bool
     */
    public function hasItems(Source $source): bool
    {
        return $source->items()->exists();
    }

    /**
     * @param Source $source
     * @return Source
     */
    public function lockForUpdate(Source $source): Source
    {
        $this->assertModel($source);

        /** @var Source $locked */
        $locked = $this->query()
            ->whereKey($source->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $locked;
    }

    /**
     * @param DataTransferObject $dto
     * @return Source
     * @throws \Throwable
     */
    public function create(DataTransferObject $dto): Source
    {
        if (! $dto instanceof SourceData) {
            throw new InvalidArgumentException(sprintf(
                '%s::create expects %s; %s given.',
                self::class,
                SourceData::class,
                $dto::class,
            ));
        }

        return DB::transaction(function () use ($dto): Source {
            /** @var Source $source */
            $source = parent::create($dto);
            $source->categories()->sync($dto->categoryIds());

            return $source->load('categories');
        });
    }

    /**
     * @param Model $model
     * @param DataTransferObject $dto
     * @return Source
     * @throws \Throwable
     */
    public function update(Model $model, DataTransferObject $dto): Source
    {
        $this->assertModel($model);

        if ($dto instanceof SourceStatusData) {
            /** @var Source $source */
            $source = parent::update($model, $dto);

            return $source;
        }

        if (! $dto instanceof SourceData) {
            throw new InvalidArgumentException(sprintf(
                '%s::update expects %s or %s; %s given.',
                self::class,
                SourceData::class,
                SourceStatusData::class,
                $dto::class,
            ));
        }

        return DB::transaction(function () use ($model, $dto): Source {
            /** @var Source $source */
            $source = parent::update($model, $dto);
            $source->categories()->sync($dto->categoryIds());

            return $source->load('categories');
        });
    }

    protected function modelClass(): string
    {
        return Source::class;
    }

    /** @return non-empty-list<class-string<SourceData|SourceStatusData>> */
    protected function dtoClasses(): array
    {
        return [SourceData::class, SourceStatusData::class];
    }
}
