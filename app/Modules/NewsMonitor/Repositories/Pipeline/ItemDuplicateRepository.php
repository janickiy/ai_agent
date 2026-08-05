<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Repositories\Pipeline;

use App\DTO\Pipeline\ItemDuplicateData;
use App\Modules\NewsMonitor\Models\ItemDuplicate;
use App\Repositories\BaseRepository;

/**
 * Управляет результатами проверки материалов на дублирование.
 *
 * Репозиторий связывает дубликат с оригиналом и скрывает идемпотентную запись
 * метода сравнения, коэффициента сходства и диагностических данных.
 *
 * @extends BaseRepository<ItemDuplicate, ItemDuplicateData>
 */
final class ItemDuplicateRepository extends BaseRepository
{
    public function __construct(ItemDuplicate $model)
    {
        parent::__construct($model);
    }

    /**
     * Создаёт или обновляет результат проверки материала на дубликат.
     *
     * Единственная запись на материал позволяет повторять анализ и сохранять
     * актуальный оригинал, метод сравнения и оценку сходства.
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

    /**
     * Указывает базовому репозиторию модель дубликата для проверки типов и построения запросов.
     *
     * @return class-string<ItemDuplicate>
     */
    protected function modelClass(): string
    {
        return ItemDuplicate::class;
    }

    /**
     * Определяет DTO, разрешённый для записи результатов поиска дубликатов.
     *
     * @return non-empty-list<class-string<ItemDuplicateData>>
     */
    protected function dtoClasses(): array
    {
        return [ItemDuplicateData::class];
    }
}
