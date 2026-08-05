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

/**
 * Управляет исходными материалами, найденными во внешних новостных источниках.
 *
 * Репозиторий отвечает за безопасное создание и обновление материалов, загрузку
 * связей и поиск точных либо семантических кандидатов на дублирование.
 *
 * @extends BaseRepository<SourceItem, SourceItemData>
 */
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

    /**
     * Инициализирует репозиторий моделью исходного материала.
     */
    public function __construct(SourceItem $model)
    {
        parent::__construct($model);
    }

    /**
     * Создаёт исходный материал только из подходящего DTO с обязательными полями обнаружения.
     *
     * Дополнительная проверка не допускает сохранение материала без источника,
     * канонического URL, его хеша и времени обнаружения.
     *
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
     * Обновляет исходный материал через типобезопасную операцию базового репозитория.
     *
     * Конкретный тип результата сохранён в сигнатуре для удобства сервисов конвейера.
     *
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
     * Находит материал по хешу канонического URL либо создаёт его из данных обнаружения.
     *
     * Метод делает повторный обход одной ленты идемпотентным и не создаёт дубликаты URL.
     *
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
     * Загружает материал вместе с источником для выполнения фонового задания обработки.
     *
     * Возвращение `null` позволяет заданию безопасно завершиться, если запись была удалена.
     *
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
     * Гарантирует загрузку связи материала с источником без повторного запроса,
     * если отношение уже было загружено вызывающим кодом.
     *
     * @param SourceItem $item
     * @return SourceItem
     */
    public function withSource(SourceItem $item): SourceItem
    {
        $this->assertModel($item);

        return $item->loadMissing('source');
    }

    /**
     * Ищет другой материал с тем же хешем канонического URL, исключая текущую запись.
     *
     * Метод выявляет URL-дубликаты при повторной нормализации уже сохранённого материала.
     *
     * @param int|string $excludedId
     * @param string $hash
     * @return SourceItem|null
     */
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
     * Ищет ранее принятый материал с совпадающим хешем заголовка и описания либо полного текста.
     *
     * Текущий материал исключается, а поиск ограничивается уже принятыми или отмеченными
     * дубликатами записями для надёжного определения оригинала.
     *
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
     * Возвращает ограниченный набор принятых материалов той же категории и периода
     * для более дорогого семантического сравнения через AI-провайдер.
     *
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

    /**
     * Указывает базовому репозиторию модель исходного материала для проверки типов.
     *
     * @return class-string<SourceItem>
     */
    protected function modelClass(): string
    {
        return SourceItem::class;
    }

    /**
     * Определяет DTO, разрешённый для создания и обновления исходных материалов.
     *
     * @return non-empty-list<class-string<SourceItemData>>
     */
    protected function dtoClasses(): array
    {
        return [SourceItemData::class];
    }
}
