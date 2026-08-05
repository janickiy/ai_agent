<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Repositories\Pipeline;

use App\DTO\Pipeline\ItemAnalysisData;
use App\Modules\NewsMonitor\Models\ItemAnalysis;
use App\Repositories\BaseRepository;

/**
 * Управляет сохранением результатов AI-анализа исходных материалов.
 *
 * Репозиторий изолирует запросы к таблице анализов и обеспечивает идемпотентную
 * запись одного актуального результата для каждого материала.
 *
 * @extends BaseRepository<ItemAnalysis, ItemAnalysisData>
 */
final class ItemAnalysisRepository extends BaseRepository
{

    public function __construct(ItemAnalysis $model)
    {
        parent::__construct($model);
    }

    /**
     * Создаёт или обновляет единственный результат анализа для указанного материала.
     *
     * Upsert нужен для безопасного повторного запуска конвейера без появления
     * нескольких записей анализа для одного исходного материала.
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

    /**
     * Указывает базовому репозиторию модель, с которой разрешено выполнять операции.
     *
     * @return class-string<ItemAnalysis>
     */
    protected function modelClass(): string
    {
        return ItemAnalysis::class;
    }

    /**
     * Определяет DTO, допустимый для операций записи результатов анализа.
     *
     * @return non-empty-list<class-string<ItemAnalysisData>>
     */
    protected function dtoClasses(): array
    {
        return [ItemAnalysisData::class];
    }
}
