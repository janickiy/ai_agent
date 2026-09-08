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
 * Endpoint хранится в общих системных настройках, а API-ключ — отдельно и только
 * в зашифрованном виде. При отсутствии настройки используется штатный адрес API.
 */
final class KaboomSettings
{
    public const ENDPOINT = 'https://api.bath.kaboom.pro/api/instroygram/news';

    private const SETTINGS_KEY = 'publishing.kaboom';

    private const CREDENTIALS_KEY = 'publishing.kaboom.credentials';

    private ?string $apiKey = null;

    private bool $apiKeyLoaded = false;

    /**
     * Инициализирует сервис репозиторием системных настроек.
     */
    public function __construct(private readonly SystemSettingRepository $settings) {}

    /**
     * Возвращает сохранённый endpoint Kaboom либо штатный адрес по умолчанию.
     */
    public function endpoint(): string
    {
        $stored = $this->settings->find(self::SETTINGS_KEY)?->value;
        $endpoint = is_array($stored) ? trim((string) ($stored['endpoint'] ?? '')) : '';

        return $endpoint === '' ? self::ENDPOINT : $endpoint;
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

        $stored = $this->settings->find(self::CREDENTIALS_KEY)?->value;
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
     * Формирует данные подключения для административной формы.
     *
     * Расшифрованный API-ключ включается только для администратора с правом изменения
     * настроек. В режиме просмотра метод возвращает пустое значение секрета.
     *
     * @return array{endpoint: string, api_key: string, api_key_configured: bool, decryption_error: bool}
     */
    public function adminValues(bool $includeApiKey = false): array
    {
        try {
            $apiKey = $this->apiKey();

            return [
                'endpoint' => $this->endpoint(),
                'api_key' => $includeApiKey ? $apiKey : '',
                'api_key_configured' => $apiKey !== '',
                'decryption_error' => false,
            ];
        } catch (RuntimeException) {
            return [
                'endpoint' => $this->endpoint(),
                'api_key' => '',
                'api_key_configured' => false,
                'decryption_error' => true,
            ];
        }
    }

    /**
     * Создаёт безопасный снимок настроек Kaboom для журнала аудита.
     *
     * @return array{endpoint: string, api_key: string, api_key_configured: bool, decryption_error: bool}
     */
    public function auditSnapshot(): array
    {
        return $this->adminValues();
    }

    /**
     * Сохраняет endpoint и новый API-ключ. Пустой ключ оставляет прежнее значение,
     * а явный флаг очистки полностью удаляет сохранённый секрет.
     */
    public function update(KaboomSettingsData $data): void
    {
        $endpoint = $data->endpoint === '' ? $this->endpoint() : $data->endpoint;
        $this->settings->put(SystemSettingData::fromArray([
            'key' => self::SETTINGS_KEY,
            'value' => ['endpoint' => $endpoint],
            'is_secret' => false,
        ]));

        if ($data->clearApiKey) {
            $setting = $this->settings->find(self::CREDENTIALS_KEY);
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
            'key' => self::CREDENTIALS_KEY,
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
