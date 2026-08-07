<?php

declare(strict_types=1);

namespace App\DTO\Publishing;

use App\DTO\DataTransferObject;
use DateTimeInterface;

/**
 * Описывает неизменяемый набор полей одной новости для API Инстройграма Kaboom.
 *
 * DTO формирует точные имена multipart-полей внешнего контракта и не содержит
 * секретный X-API-Key, который клиент получает только из зашифрованных настроек.
 */
final readonly class KaboomPublicationData extends DataTransferObject
{
    /**
     * Сохраняет скопированные поля новости и название локальной категории.
     *
     * @param  list<string>  $hashtags
     */
    public function __construct(
        public string $uid,
        public string $title,
        public DateTimeInterface $published,
        public string $fullDescription,
        public string $shortDescription,
        public string $url,
        public string $publicationSource,
        public string $category,
        public array $hashtags,
        public ?string $imageUrl,
    ) {}

    /**
     * Возвращает multipart-поля в формате API Kaboom.
     *
     * Хэштеги передаются одной строкой через запятую, а отсутствующая картинка
     * исключается из запроса, чтобы повторная доставка не удаляла прежнее изображение.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $fields = [
            'uid' => $this->uid,
            'title' => $this->title,
            'published' => $this->published->format(DATE_ATOM),
            'full_description' => $this->fullDescription,
            'short_description' => $this->shortDescription,
            'url' => $this->url,
            'publication_source' => $this->publicationSource,
            'category' => $this->category,
            'hashtags' => implode(',', $this->hashtags),
        ];

        if ($this->imageUrl !== null && $this->imageUrl !== '') {
            $fields['image_url'] = $this->imageUrl;
        }

        return $fields;
    }
}
