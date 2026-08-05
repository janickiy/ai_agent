<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Repositories\Pipeline;

use App\DTO\Pipeline\PublicationPostData;
use App\Modules\NewsMonitor\Models\PublicationPost;
use App\Repositories\BaseRepository;

/**
 * Управляет публикациями, подготовленными из принятых новостных материалов.
 *
 * Репозиторий инкапсулирует поиск и идемпотентное создание поста, чтобы один
 * исходный материал не породил несколько готовых публикаций.
 *
 * @extends BaseRepository<PublicationPost, PublicationPostData>
 */
final class PublicationPostRepository extends BaseRepository
{
    public function __construct(PublicationPost $model)
    {
        parent::__construct($model);
    }

    /**
     * Находит готовую публикацию по идентификатору исходного материала.
     *
     * Метод используется для проверки идемпотентности перед повторным формированием поста.
     */
    public function findBySourceItemId(int $sourceItemId): ?PublicationPost
    {
        /** @var PublicationPost|null $post */
        $post = $this->query()->where('source_item_id', $sourceItemId)->first();

        return $post;
    }

    /**
     * Возвращает существующую публикацию материала либо атомарно создаёт её из DTO.
     *
     * Уникальность по исходному материалу предотвращает повторную публикацию новости.
     */
    public function firstOrCreateForSourceItem(PublicationPostData $dto): PublicationPost
    {
        $attributes = $dto->toArray();
        unset($attributes['source_item_id']);

        /** @var PublicationPost $post */
        $post = $this->query()->firstOrCreate(
            ['source_item_id' => $dto->sourceItemId],
            $attributes,
        );

        return $post;
    }

    /**
     * Указывает базовому репозиторию модель публикации для проверки типов и CRUD-операций.
     *
     * @return class-string<PublicationPost>
     */
    protected function modelClass(): string
    {
        return PublicationPost::class;
    }

    /**
     * Определяет DTO, разрешённый для записи подготовленных публикаций.
     *
     * @return non-empty-list<class-string<PublicationPostData>>
     */
    protected function dtoClasses(): array
    {
        return [PublicationPostData::class];
    }
}
