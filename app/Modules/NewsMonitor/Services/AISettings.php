<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Services;

use App\DTO\Settings\AISettingsData;
use App\DTO\System\SystemSettingData;
use App\Modules\NewsMonitor\Repositories\System\SystemSettingRepository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Управляет выбором AI-провайдера, его публичными настройками и секретными реквизитами.
 *
 * Сервис объединяет значения из БД с безопасными значениями по умолчанию, шифрует ключи,
 * формирует конфигурацию провайдеров и не допускает раскрытия секретов в админке и аудите.
 */
final class AISettings
{
    private const PUBLIC_KEY = 'ai';

    private const CREDENTIAL_KEYS = [
        'gigachat' => 'ai.gigachat.credentials',
        'yandexgpt' => 'ai.yandexgpt.credentials',
        'openai' => 'ai.openai.credentials',
    ];

    private const CREDENTIAL_FIELDS = [
        'gigachat' => ['auth_key', 'client_id', 'client_secret'],
        'yandexgpt' => ['api_key', 'iam_token'],
        'openai' => ['api_key'],
    ];

    private const PROVIDER_LABELS = [
        'gigachat' => 'GigaChat',
        'yandexgpt' => 'YandexGPT',
        'openai' => 'OpenAI',
    ];

    private const PROVIDERS = [
        'rules' => ['label' => 'Локальные правила', 'available' => true],
        'gigachat' => ['label' => 'GigaChat', 'available' => true],
        'yandexgpt' => ['label' => 'YandexGPT', 'available' => true],
        'openai' => ['label' => 'OpenAI', 'available' => true],
    ];

    /** @var array<string, mixed>|null */
    private ?array $publicValues = null;

    /** @var array<string, array<string, string>> */
    private array $credentialValues = [];

    /**
     * Инициализирует сервис репозиторием системных настроек для чтения и записи конфигурации AI.
     */
    public function __construct(private readonly SystemSettingRepository $settings) {}

    /**
     * Возвращает полный справочник AI-провайдеров для построения выбора в админке.
     *
     * @return array<string, array{label: string, available: bool}>
     */
    public static function providerOptions(): array
    {
        return self::PROVIDERS;
    }

    /**
     * Возвращает коды провайдеров, которые разрешено активировать в текущей версии системы.
     *
     * @return list<string>
     */
    public static function availableProviderCodes(): array
    {
        return array_keys(array_filter(
            self::PROVIDERS,
            static fn (array $provider): bool => $provider['available'],
        ));
    }

    /**
     * Возвращает код активного AI-провайдера из сохранённых публичных настроек.
     */
    public function provider(): string
    {
        return (string) $this->public()['provider'];
    }

    /**
     * Формирует полную конфигурацию GigaChat, включая расшифрованные реквизиты для API-клиента.
     *
     * @return array<string, mixed>
     */
    public function gigachatConfig(): array
    {
        return $this->providerConfig('gigachat');
    }

    /**
     * Формирует полную конфигурацию YandexGPT, включая расшифрованные реквизиты для API-клиента.
     *
     * @return array<string, mixed>
     */
    public function yandexgptConfig(): array
    {
        return $this->providerConfig('yandexgpt');
    }

    /**
     * Формирует полную конфигурацию OpenAI, включая расшифрованный API-ключ.
     *
     * @return array<string, mixed>
     */
    public function openaiConfig(): array
    {
        return $this->providerConfig('openai');
    }

    /**
     * Подготавливает безопасные значения для формы администрирования.
     *
     * Вместо самих секретов метод возвращает только признаки их наличия и ошибки расшифровки.
     *
     * @return array<string, mixed>
     */
    public function adminValues(): array
    {
        $public = $this->public();

        return [
            'provider' => $public['provider'],
            'gigachat' => [
                ...$public['gigachat'],
                ...$this->credentialStatus('gigachat'),
            ],
            'yandexgpt' => [
                ...$public['yandexgpt'],
                ...$this->credentialStatus('yandexgpt'),
            ],
            'openai' => [
                ...$public['openai'],
                ...$this->credentialStatus('openai'),
            ],
        ];
    }

    /**
     * Создаёт безопасный снимок AI-настроек для журнала аудита.
     *
     * Конфиденциальные значения заменяются статусами настройки, поэтому ключи и токены
     * никогда не попадают в историю изменений.
     *
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        $values = $this->adminValues();
        $snapshot = ['provider' => $values['provider']];

        foreach (array_keys(self::CREDENTIAL_KEYS) as $provider) {
            $configuredKeys = array_map(
                static fn (string $field): string => "{$field}_configured",
                self::CREDENTIAL_FIELDS[$provider],
            );
            $statusKeys = [...$configuredKeys, 'credentials_decryption_error'];
            $snapshot[$provider] = [
                ...array_diff_key($values[$provider], array_flip($statusKeys)),
                'credentials' => [
                    ...array_intersect_key($values[$provider], array_flip($configuredKeys)),
                    'decryption_error' => $values[$provider]['credentials_decryption_error'],
                ],
            ];
        }

        return $snapshot;
    }

    /**
     * Проверяет и сохраняет выбор провайдера, публичные параметры и секретные реквизиты.
     *
     * Метод валидирует обязательные ключи активного провайдера, шифрует секреты,
     * выполняет запись транзакционно и сбрасывает локальные кеши настроек и токена.
     */
    public function update(AISettingsData $data): void
    {
        $provider = $data->provider;
        if (! in_array($provider, self::availableProviderCodes(), true)) {
            throw new InvalidArgumentException("Unsupported AI provider: {$provider}");
        }

        $public = [
            'provider' => $provider,
            'gigachat' => $this->normalizeGigachat($data->providerSettings['gigachat']),
            'yandexgpt' => $this->normalizeYandexgpt($data->providerSettings['yandexgpt']),
            'openai' => $this->normalizeOpenai($data->providerSettings['openai']),
        ];
        $credentials = [];

        foreach (array_keys(self::CREDENTIAL_KEYS) as $providerCode) {
            $credentials[$providerCode] = $this->mergeCredentials(
                $providerCode,
                $data->providerCredentials[$providerCode] ?? [],
                (bool) ($data->clearCredentials[$providerCode] ?? false),
            );
        }

        $gigachatCredentials = $credentials['gigachat'];
        if (
            $provider === 'gigachat'
            && $gigachatCredentials['auth_key'] === ''
            && ($gigachatCredentials['client_id'] === '' || $gigachatCredentials['client_secret'] === '')
        ) {
            throw new InvalidArgumentException(
                'GigaChat requires an Authorization Key or a Client ID / Client Secret pair.',
            );
        }

        $yandexCredentials = $credentials['yandexgpt'];
        if ($provider === 'yandexgpt') {
            if ($public['yandexgpt']['folder_id'] === '') {
                throw new InvalidArgumentException('YandexGPT requires a Folder ID.');
            }
            if ($yandexCredentials['api_key'] === '' && $yandexCredentials['iam_token'] === '') {
                throw new InvalidArgumentException('YandexGPT requires an API Key or IAM token.');
            }
        }

        if ($provider === 'openai' && $credentials['openai']['api_key'] === '') {
            throw new InvalidArgumentException('OpenAI requires an API Key.');
        }

        DB::transaction(function () use ($public, $credentials): void {
            $this->settings->put(SystemSettingData::fromArray([
                'key' => self::PUBLIC_KEY,
                'value' => $public,
                'is_secret' => false,
            ]));

            foreach (self::CREDENTIAL_KEYS as $providerCode => $settingKey) {
                $this->settings->put(SystemSettingData::fromArray([
                    'key' => $settingKey,
                    'value' => array_map(
                        fn (string $value): ?string => $this->encrypt($value),
                        $credentials[$providerCode],
                    ),
                    'is_secret' => true,
                ]));
            }
        });

        $this->publicValues = null;
        $this->credentialValues = [];
        Cache::forget('gigachat:access-token');
    }

    /**
     * Объединяет публичные параметры указанного провайдера с его расшифрованными реквизитами.
     *
     * Метод используется только при создании рабочего API-клиента внутри приложения.
     *
     * @return array<string, mixed>
     */
    private function providerConfig(string $provider): array
    {
        return [
            ...$this->public()[$provider],
            ...$this->credentials($provider),
        ];
    }

    /**
     * Загружает, нормализует и кеширует публичную часть AI-настроек.
     *
     * Повреждённые или отсутствующие значения заменяются безопасными настройками по умолчанию.
     *
     * @return array<string, mixed>
     */
    private function public(): array
    {
        if ($this->publicValues !== null) {
            return $this->publicValues;
        }

        $stored = $this->settings->find(self::PUBLIC_KEY)?->value;
        $defaults = $this->defaults();
        if (! is_array($stored)) {
            return $this->publicValues = $defaults;
        }

        $provider = (string) ($stored['provider'] ?? $defaults['provider']);
        if (! in_array($provider, self::availableProviderCodes(), true)) {
            $provider = 'rules';
        }

        return $this->publicValues = [
            'provider' => $provider,
            'gigachat' => $this->normalizeGigachat(
                is_array($stored['gigachat'] ?? null) ? $stored['gigachat'] : [],
            ),
            'yandexgpt' => $this->normalizeYandexgpt(
                is_array($stored['yandexgpt'] ?? null) ? $stored['yandexgpt'] : [],
            ),
            'openai' => $this->normalizeOpenai(
                is_array($stored['openai'] ?? null) ? $stored['openai'] : [],
            ),
        ];
    }

    /**
     * Загружает и расшифровывает реквизиты выбранного провайдера с кешированием в рамках запроса.
     *
     * @return array<string, string>
     */
    private function credentials(string $provider): array
    {
        if (isset($this->credentialValues[$provider])) {
            return $this->credentialValues[$provider];
        }

        $setting = $this->settings->find(self::CREDENTIAL_KEYS[$provider]);
        $stored = $setting?->value;
        if ($setting !== null && ! is_array($stored)) {
            throw $this->credentialException($provider, 'Повреждён формат хранилища учётных данных');
        }
        $stored ??= [];

        $credentials = [];
        foreach (self::CREDENTIAL_FIELDS[$provider] as $field) {
            $credentials[$field] = $this->decrypt($provider, $stored[$field] ?? null);
        }

        return $this->credentialValues[$provider] = $credentials;
    }

    /**
     * Возвращает признаки наличия реквизитов для интерфейса без передачи их значений.
     *
     * Ошибка расшифровки преобразуется в отдельный статус, позволяющий заменить повреждённые данные.
     *
     * @return array<string, bool>
     */
    private function credentialStatus(string $provider): array
    {
        try {
            $credentials = $this->credentials($provider);
            $status = [];
            foreach ($credentials as $key => $value) {
                $status["{$key}_configured"] = $value !== '';
            }
            $status['credentials_decryption_error'] = false;

            return $status;
        } catch (RuntimeException) {
            $status = [];
            foreach (self::CREDENTIAL_FIELDS[$provider] as $key) {
                $status["{$key}_configured"] = false;
            }
            $status['credentials_decryption_error'] = true;

            return $status;
        }
    }

    /**
     * Объединяет новые реквизиты с уже сохранёнными либо полностью очищает их по явному флагу.
     *
     * Пустые поля формы не стирают существующие секреты, а связанные Client ID и Client Secret
     * GigaChat разрешено заменять только одновременно.
     *
     * @param  array<string, mixed>  $replacement
     * @return array<string, string>
     */
    private function mergeCredentials(string $provider, array $replacement, bool $clear): array
    {
        $empty = array_fill_keys(self::CREDENTIAL_FIELDS[$provider], '');
        if ($clear) {
            return $empty;
        }

        $newValues = [];
        foreach (self::CREDENTIAL_FIELDS[$provider] as $field) {
            $newValues[$field] = trim((string) ($replacement[$field] ?? ''));
        }

        if ($provider === 'gigachat' && (($newValues['client_id'] === '') !== ($newValues['client_secret'] === ''))) {
            throw new InvalidArgumentException('Client ID and Client Secret must be replaced together.');
        }

        try {
            $stored = $this->credentials($provider);
        } catch (RuntimeException $exception) {
            if (! $this->canReplaceCorruptedCredentials($provider, $newValues)) {
                throw $exception;
            }
            $stored = $empty;
        }

        foreach ($newValues as $field => $value) {
            if ($value !== '') {
                $stored[$field] = $value;
            }
        }

        return $stored;
    }

    /**
     * Проверяет, достаточно ли новых реквизитов для полной замены повреждённого хранилища секретов.
     *
     * @param  array<string, string>  $credentials
     */
    private function canReplaceCorruptedCredentials(string $provider, array $credentials): bool
    {
        if ($provider === 'gigachat') {
            return $credentials['auth_key'] !== ''
                || ($credentials['client_id'] !== '' && $credentials['client_secret'] !== '');
        }

        return array_any($credentials, static fn (string $value): bool => $value !== '');
    }

    /**
     * Приводит публичные настройки GigaChat к ожидаемым типам и принудительно включает проверку TLS.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalizeGigachat(array $values): array
    {
        $values = array_replace($this->defaults()['gigachat'], $values);

        return [
            'auth_url' => trim((string) $values['auth_url']),
            'api_url' => trim((string) $values['api_url']),
            'scope' => trim((string) $values['scope']),
            'model' => trim((string) $values['model']),
            'embedding_model' => trim((string) $values['embedding_model']),
            'embedding_fallback' => (bool) $values['embedding_fallback'],
            'timeout' => (int) $values['timeout'],
            'connect_timeout' => (int) $values['connect_timeout'],
            'max_attempts' => (int) $values['max_attempts'],
            'verify_ssl' => true,
        ];
    }

    /**
     * Приводит публичные настройки YandexGPT к ожидаемым типам и принудительно включает проверку TLS.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalizeYandexgpt(array $values): array
    {
        $values = array_replace($this->defaults()['yandexgpt'], $values);

        return [
            'api_url' => trim((string) $values['api_url']),
            'folder_id' => trim((string) $values['folder_id']),
            'model' => trim((string) $values['model']),
            'embedding_model' => trim((string) $values['embedding_model']),
            'timeout' => (int) $values['timeout'],
            'connect_timeout' => (int) $values['connect_timeout'],
            'max_attempts' => (int) $values['max_attempts'],
            'verify_ssl' => true,
        ];
    }

    /**
     * Приводит публичные настройки OpenAI к ожидаемым типам и принудительно включает проверку TLS.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalizeOpenai(array $values): array
    {
        $values = array_replace($this->defaults()['openai'], $values);

        return [
            'api_url' => trim((string) $values['api_url']),
            'model' => trim((string) $values['model']),
            'embedding_model' => trim((string) $values['embedding_model']),
            'organization' => trim((string) $values['organization']),
            'project' => trim((string) $values['project']),
            'timeout' => (int) $values['timeout'],
            'connect_timeout' => (int) $values['connect_timeout'],
            'max_attempts' => (int) $values['max_attempts'],
            'verify_ssl' => true,
        ];
    }

    /**
     * Возвращает безопасную конфигурацию AI из файлов приложения для первого запуска
     * и восстановления отсутствующих полей сохранённых настроек.
     *
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'provider' => 'rules',
            'gigachat' => (array) config('ai.providers.gigachat'),
            'yandexgpt' => (array) config('ai.providers.yandexgpt'),
            'openai' => (array) config('ai.providers.openai'),
        ];
    }

    /**
     * Шифрует непустое значение средствами Laravel перед записью в базу данных.
     *
     * Пустой секрет сохраняется как `null`, чтобы явно обозначить отсутствие реквизита.
     */
    private function encrypt(string $value): ?string
    {
        return $value === '' ? null : Crypt::encryptString($value);
    }

    /**
     * Расшифровывает сохранённый секрет и преобразует повреждение данных либо несовпадение
     * `APP_KEY` в понятное исключение с названием соответствующего провайдера.
     */
    private function decrypt(string $provider, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (! is_string($value)) {
            throw $this->credentialException($provider, 'Повреждён формат учётных данных');
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $exception) {
            throw $this->credentialException(
                $provider,
                'Не удалось расшифровать учётные данные. Проверьте APP_KEY',
                $exception,
            );
        }
    }

    /**
     * Создаёт единообразное исключение настройки реквизитов с человекочитаемым именем провайдера.
     */
    private function credentialException(
        string $provider,
        string $message,
        ?DecryptException $previous = null,
    ): RuntimeException {
        return new RuntimeException(
            $message.' '.self::PROVIDER_LABELS[$provider].'.',
            previous: $previous,
        );
    }
}
