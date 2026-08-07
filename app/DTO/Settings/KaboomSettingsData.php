<?php

declare(strict_types=1);

namespace App\DTO\Settings;

use App\DTO\DataTransferObject;

/**
 * Передаёт изменение секретного API-ключа интеграции Kaboom из формы в сервис настроек.
 *
 * Пустой ключ означает сохранение текущего значения, а флаг очистки — его явное удаление.
 */
final readonly class KaboomSettingsData extends DataTransferObject
{
    /**
     * Создаёт DTO с новым значением API-ключа и признаком его удаления.
     */
    public function __construct(
        public string $apiKey,
        public bool $clearApiKey,
    ) {}

    /**
     * Нормализует данные формы настроек Kaboom перед передачей в сервис.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            apiKey: trim((string) ($data['api_key'] ?? '')),
            clearApiKey: (bool) ($data['clear_api_key'] ?? false),
        );
    }

    /**
     * Возвращает нормализованные данные изменения настроек Kaboom.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'api_key' => $this->apiKey,
            'clear_api_key' => $this->clearApiKey,
        ];
    }
}
