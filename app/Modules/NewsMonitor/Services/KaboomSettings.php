<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Services;

use App\DTO\Settings\KaboomSettingsData;
use App\DTO\System\SystemSettingData;
use App\Modules\NewsMonitor\Repositories\System\SystemSettingRepository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/**
 * Управляет защищёнными реквизитами подключения к API публикации новостей Kaboom.
 *
 * Endpoint зафиксирован в коде, а API-ключ хранится в системных настройках только
 * в зашифрованном виде и раскрывается исключительно серверному API-клиенту.
 */
final class KaboomSettings
{
    public const ENDPOINT = 'https://api.bath.kaboom.pro/api/instroygram/news';

    private const KEY = 'publishing.kaboom.credentials';

    private ?string $apiKey = null;

    private bool $apiKeyLoaded = false;

    /**
     * Инициализирует сервис репозиторием системных настроек.
     */
    public function __construct(private readonly SystemSettingRepository $settings) {}

    /**
     * Возвращает единый доверенный endpoint для отправки публикаций Kaboom.
     */
    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    /**
     * Возвращает расшифрованный API-ключ для заголовка `X-API-Key` серверного запроса.
     *
     * Пустая строка означает, что ключ ещё не настроен. Повреждённый шифротекст или
     * несовпадающий `APP_KEY` преобразуется в понятное исключение конфигурации.
     */
    public function apiKey(): string
    {
        if ($this->apiKeyLoaded) {
            return $this->apiKey ?? '';
        }

        $stored = $this->settings->find(self::KEY)?->value;
        if ($stored === null) {
            $this->apiKeyLoaded = true;

            return $this->apiKey = '';
        }
        if (! is_array($stored) || ! array_key_exists('api_key', $stored)) {
            throw new RuntimeException('Повреждён формат API-ключа Kaboom в системных настройках.');
        }

        $encrypted = $stored['api_key'];
        if (! is_string($encrypted) || $encrypted === '') {
            throw new RuntimeException('Повреждён формат API-ключа Kaboom в системных настройках.');
        }

        try {
            $this->apiKey = Crypt::decryptString($encrypted);
            $this->apiKeyLoaded = true;

            return $this->apiKey;
        } catch (DecryptException $exception) {
            throw new RuntimeException(
                'Не удалось расшифровать API-ключ Kaboom. Проверьте APP_KEY или сохраните новый ключ.',
                previous: $exception,
            );
        }
    }

    /**
     * Формирует безопасные данные для формы настроек без передачи API-ключа в HTML.
     *
     * @return array{endpoint: string, api_key_configured: bool, decryption_error: bool}
     */
    public function adminValues(): array
    {
        try {
            return [
                'endpoint' => self::ENDPOINT,
                'api_key_configured' => $this->apiKey() !== '',
                'decryption_error' => false,
            ];
        } catch (RuntimeException) {
            return [
                'endpoint' => self::ENDPOINT,
                'api_key_configured' => false,
                'decryption_error' => true,
            ];
        }
    }

    /**
     * Создаёт безопасный снимок настроек Kaboom для журнала аудита.
     *
     * @return array{endpoint: string, api_key_configured: bool, decryption_error: bool}
     */
    public function auditSnapshot(): array
    {
        return $this->adminValues();
    }

    /**
     * Сохраняет новый API-ключ зашифрованным, оставляет прежний при пустом поле
     * либо полностью удаляет секрет по явному флагу очистки.
     */
    public function update(KaboomSettingsData $data): void
    {
        if ($data->clearApiKey) {
            $setting = $this->settings->find(self::KEY);
            if ($setting !== null) {
                $this->settings->delete($setting);
            }

            $this->resetCache();

            return;
        }

        if ($data->apiKey === '') {
            return;
        }

        $this->settings->put(SystemSettingData::fromArray([
            'key' => self::KEY,
            'value' => ['api_key' => Crypt::encryptString($data->apiKey)],
            'is_secret' => true,
        ]));
        $this->resetCache();
    }

    /**
     * Сбрасывает расшифрованное значение после изменения записи в БД.
     */
    private function resetCache(): void
    {
        $this->apiKey = null;
        $this->apiKeyLoaded = false;
    }
}
