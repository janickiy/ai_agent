<?php

declare(strict_types=1);

namespace App\DTO\Pipeline;

use App\DTO\DataTransferObject;
use DateTimeInterface;

/**
 * Переносит подтверждённые Kaboom данные опубликованного поста в репозиторий.
 */
final readonly class PublicationPostData extends DataTransferObject
{
    /**
     * @param  list<string>  $hashtags
     * @param  array<string, mixed>  $validationMeta
     */
    public function __construct(
        public int $sourceItemId,
        public string $uid,
        public string $idempotencyKey,
        public string $sourceUrl,
        public string $sourceName,
        public DateTimeInterface $sourcePublishedAt,
        public string $titleOriginal,
        public string $descriptionOriginal,
        public string $fullDescriptionOriginal,
        public ?string $imageUrl,
        public ?string $imageStorageKey,
        public string $readMoreLabel,
        public int $categoryId,
        public array $hashtags,
        public string $contentHash,
        public string $status,
        public array $validationMeta,
        public DateTimeInterface $readyAt,
        public DateTimeInterface $exportedAt,
    ) {}

    /**
     * Возвращает атрибуты локальной записи, создаваемой только после успешной отправки.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_item_id' => $this->sourceItemId,
            'uid' => $this->uid,
            'idempotency_key' => $this->idempotencyKey,
            'source_url' => $this->sourceUrl,
            'source_name' => $this->sourceName,
            'source_published_at' => $this->sourcePublishedAt,
            'title_original' => $this->titleOriginal,
            'description_original' => $this->descriptionOriginal,
            'full_description_original' => $this->fullDescriptionOriginal,
            'image_url' => $this->imageUrl,
            'image_storage_key' => $this->imageStorageKey,
            'read_more_label' => $this->readMoreLabel,
            'category_id' => $this->categoryId,
            'hashtags' => $this->hashtags,
            'content_hash' => $this->contentHash,
            'status' => $this->status,
            'validation_meta' => $this->validationMeta,
            'ready_at' => $this->readyAt,
            'exported_at' => $this->exportedAt,
        ];
    }
}
