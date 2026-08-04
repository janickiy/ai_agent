<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Repositories\Pipeline;

use App\DTO\Pipeline\ItemDuplicateData;
use App\Modules\NewsMonitor\Models\ItemDuplicate;
use App\Repositories\BaseRepository;

/** @extends BaseRepository<ItemDuplicate, ItemDuplicateData> */
final class ItemDuplicateRepository extends BaseRepository
{
    public function __construct(ItemDuplicate $model)
    {
        parent::__construct($model);
    }

    /**
     * @param ItemDuplicateData $dto
     * @return ItemDuplicate
     */
    public function upsertForSourceItem(ItemDuplicateData $dto): ItemDuplicate
    {
        $attributes = $dto->toArray();
        unset($attributes['source_item_id']);

        /** @var ItemDuplicate $duplicate */
        $duplicate = $this->query()->updateOrCreate(
            ['source_item_id' => $dto->sourceItemId],
            $attributes,
        );

        return $duplicate;
    }

    protected function modelClass(): string
    {
        return ItemDuplicate::class;
    }

    /** @return non-empty-list<class-string<ItemDuplicateData>> */
    protected function dtoClasses(): array
    {
        return [ItemDuplicateData::class];
    }
}
