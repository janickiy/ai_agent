<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Repositories\Pipeline;

use App\DTO\DataTransferObject;
use App\DTO\Pipeline\SourceItemData;
use App\Modules\NewsMonitor\Models\SourceItem;
use App\Repositories\BaseRepository;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/** @extends BaseRepository<SourceItem, SourceItemData> */
final class SourceItemRepository extends BaseRepository
{
    /** @var non-empty-list<string> */
    private const CREATE_FIELDS = [
        'source_id',
        'discovery_url',
        'canonical_url',
        'canonical_url_hash',
        'discovered_at',
    ];

    public function __construct(SourceItem $model)
    {
        parent::__construct($model);
    }

    /**
     * @param DataTransferObject $dto
     * @return SourceItem
     */
    public function create(DataTransferObject $dto): SourceItem
    {
        $this->assertDto($dto);
        /** @var SourceItemData $dto */
        $dto->requireFields(self::CREATE_FIELDS);

        /** @var SourceItem $item */
        $item = parent::create($dto);

        return $item;
    }

    /**
     * @param Model $model
     * @param DataTransferObject $dto
     * @return SourceItem
     */
    public function update(Model $model, DataTransferObject $dto): SourceItem
    {
        /** @var SourceItem $item */
        $item = parent::update($model, $dto);

        return $item;
    }

    /**
     * @param SourceItemData $dto
     * @return SourceItem
     */
    public function firstOrCreateByCanonicalHash(SourceItemData $dto): SourceItem
    {
        $dto->requireFields(self::CREATE_FIELDS);
        $attributes = $dto->toArray();
        $canonicalHash = (string)$attributes['canonical_url_hash'];
        unset($attributes['canonical_url_hash']);

        /** @var SourceItem $item */
        $item = $this->query()->firstOrCreate(
            ['canonical_url_hash' => $canonicalHash],
            $attributes,
        );

        return $item;
    }

    /**
     * @param int|string $id
     * @return SourceItem|null
     */
    public function findForProcessing(int|string $id): ?SourceItem
    {
        /** @var SourceItem|null $item */
        $item = $this->query()->with('source')->find($id);

        return $item;
    }

    /**
     * @param SourceItem $item
     * @return SourceItem
     */
    public function withSource(SourceItem $item): SourceItem
    {
        $this->assertModel($item);

        return $item->loadMissing('source');
    }

    public function findOtherByCanonicalUrlHash(int|string $excludedId, string $hash): ?SourceItem
    {
        /** @var SourceItem|null $item */
        $item = $this->query()
            ->whereKeyNot($excludedId)
            ->where('canonical_url_hash', $hash)
            ->first();

        return $item;
    }

    /**
     * @param int|string $excludedId
     * @param string $titleDescriptionHash
     * @param string $contentHash
     * @return SourceItem|null
     */
    public function findExactDuplicate(
        int|string $excludedId,
        string     $titleDescriptionHash,
        string     $contentHash,
    ): ?SourceItem
    {
        /** @var SourceItem|null $item */
        $item = $this->query()
            ->whereKeyNot($excludedId)
            ->where(static function (Builder $query) use ($titleDescriptionHash, $contentHash): void {
                $query->where('title_description_hash', $titleDescriptionHash)
                    ->orWhere('content_hash', $contentHash);
            })
            ->whereIn('status', ['accepted', 'duplicate'])
            ->orderBy('id')
            ->first();

        return $item;
    }

    /**
     * @param int|string $excludedId
     * @param int $categoryId
     * @param CarbonInterface $from
     * @param CarbonInterface $to
     * @param int $limit
     * @return Collection
     */
    public function semanticDuplicateCandidates(
        int|string      $excludedId,
        int             $categoryId,
        CarbonInterface $from,
        CarbonInterface $to,
        int             $limit = 20,
    ): Collection
    {
        return $this->query()
            ->whereKeyNot($excludedId)
            ->where('status', 'accepted')
            ->whereHas('analysis', static fn(Builder $query) => $query->where('category_id', $categoryId))
            ->whereBetween('source_published_at', [$from, $to])
            ->latest('source_published_at')
            ->limit(max(1, $limit))
            ->get();
    }

    protected function modelClass(): string
    {
        return SourceItem::class;
    }

    /** @return non-empty-list<class-string<SourceItemData>> */
    protected function dtoClasses(): array
    {
        return [SourceItemData::class];
    }
}
