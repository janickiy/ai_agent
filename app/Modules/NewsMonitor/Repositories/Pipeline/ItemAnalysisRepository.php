<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Repositories\Pipeline;

use App\DTO\Pipeline\ItemAnalysisData;
use App\Modules\NewsMonitor\Models\ItemAnalysis;
use App\Repositories\BaseRepository;

/** @extends BaseRepository<ItemAnalysis, ItemAnalysisData> */
final class ItemAnalysisRepository extends BaseRepository
{
    public function __construct(ItemAnalysis $model)
    {
        parent::__construct($model);
    }

    /**
     * @param ItemAnalysisData $dto
     * @return ItemAnalysis
     */
    public function upsertForSourceItem(ItemAnalysisData $dto): ItemAnalysis
    {
        $attributes = $dto->toArray();
        unset($attributes['source_item_id']);

        /** @var ItemAnalysis $analysis */
        $analysis = $this->query()->updateOrCreate(
            ['source_item_id' => $dto->sourceItemId],
            $attributes,
        );

        return $analysis;
    }

    protected function modelClass(): string
    {
        return ItemAnalysis::class;
    }

    /** @return non-empty-list<class-string<ItemAnalysisData>> */
    protected function dtoClasses(): array
    {
        return [ItemAnalysisData::class];
    }
}
