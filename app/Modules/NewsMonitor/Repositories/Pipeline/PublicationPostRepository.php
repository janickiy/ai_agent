<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Repositories\Pipeline;

use App\DTO\Pipeline\PublicationPostData;
use App\Modules\NewsMonitor\Models\PublicationPost;
use App\Repositories\BaseRepository;

/**
 * Управляет публикациями, подтверждёнными внешним API Kaboom.
 *
 * Репозиторий инкапсулирует поиск и идемпотентное создание поста, чтобы повторная
 * доставка одного UID не породила несколько локальных публикаций.
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
     * Находит подтверждённый Kaboom пост по идентификатору исходного материала.
     *
     * Старые строки со статусом `ready` не считаются опубликованными и не блокируют
     * их последующую фактическую отправку во внешний API.
     */
    public function findBySourceItemId(int $sourceItemId): ?PublicationPost
    {
        /** @var PublicationPost|null $post */
        $post = $this->query()
            ->where('source_item_id', $sourceItemId)
            ->where('status', 'exported')
            ->first();

        return $post;
    }

    /**
     * Находит уже опубликованный материал с тем же неизменённым содержимым.
     *
     * Проверка выполняется внутри распределённой блокировки перед HTTP-запросом,
     * чтобы параллельные задания не отправили один текст под разными URL.
     */
    public function findByContentHash(string $contentHash): ?PublicationPost
    {
        /** @var PublicationPost|null $post */
        $post = $this->query()
            ->where('content_hash', $contentHash)
            ->where('status', 'exported')
            ->first();

        return $post;
    }

    /**
     * Создаёт успешную публикацию либо переводит старую подготовленную строку в `exported`.
     *
     * Обновление legacy-строки выполняется только после ответа Kaboom 200/201, поэтому
     * она станет видна в административном разделе лишь после реальной доставки.
     */
    public function firstOrCreateForSourceItem(PublicationPostData $dto): PublicationPost
    {
        $existing = $this->query()->where('source_item_id', $dto->sourceItemId)->first();
        if ($existing !== null) {
            /** @var PublicationPost $post */
            $post = $this->update($existing, $dto);

            return $post;
        }

        /** @var PublicationPost $post */
        $post = $this->create($dto);

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
