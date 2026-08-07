<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Publishing;

/**
 * Представляет подтверждённый успешный ответ API Kaboom на создание или обновление новости.
 */
final readonly class KaboomPublicationResult
{
    /**
     * Хранит HTTP-код, внешний ID, UID, признак создания и диагностическое сообщение.
     */
    public function __construct(
        public int $statusCode,
        public int|string $externalId,
        public string $uid,
        public bool $created,
        public string $message,
    ) {}

    /**
     * Возвращает безопасные метаданные результата для локальной публикации и журнала.
     *
     * @return array<string, int|string|bool>
     */
    public function toArray(): array
    {
        return [
            'status_code' => $this->statusCode,
            'external_id' => $this->externalId,
            'uid' => $this->uid,
            'created' => $this->created,
            'message' => $this->message,
        ];
    }
}
