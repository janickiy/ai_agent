<?php

declare(strict_types=1);

namespace App\Modules\Admin\Requests;

use App\Modules\NewsMonitor\Services\AISettings;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'automatic_publication' => ['boolean'],
            'max_news_age_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'event_similarity_threshold' => ['required', 'numeric', 'min:0', 'max:1'],
            'ai_provider' => ['required', 'string', Rule::in(AISettings::availableProviderCodes())],
            'settings_tab' => ['required', 'string', Rule::in(['gigachat', 'yandexgpt', 'openai', 'gemini'])],
            'gigachat_auth_url' => [
                'required',
                'url',
                'starts_with:https://',
                'max:2048',
                $this->officialEndpoint('ngw.devices.sberbank.ru', 9443),
            ],
            'gigachat_api_url' => [
                'required',
                'url',
                'starts_with:https://',
                'max:2048',
                $this->officialEndpoint('api.giga.chat', 443),
            ],
            'gigachat_auth_key' => ['nullable', 'string', 'max:4096'],
            'gigachat_client_id' => ['nullable', 'string', 'max:512'],
            'gigachat_client_secret' => ['nullable', 'string', 'max:4096'],
            'gigachat_scope' => ['required', 'string', 'max:255'],
            'gigachat_model' => ['required', 'string', 'max:255'],
            'gigachat_embedding_model' => ['required', 'string', 'max:255'],
            'gigachat_embedding_fallback' => ['boolean'],
            'gigachat_timeout' => ['required', 'integer', 'min:1', 'max:600'],
            'gigachat_connect_timeout' => ['required', 'integer', 'min:1', 'max:120', 'lte:gigachat_timeout'],
            'gigachat_max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'gigachat_verify_ssl' => ['accepted'],
            'clear_gigachat_secrets' => ['boolean'],
            'yandexgpt_api_url' => [
                'required',
                'url',
                'starts_with:https://',
                'max:2048',
                $this->officialEndpoint('ai.api.cloud.yandex.net', 443),
            ],
            'yandexgpt_folder_id' => ['nullable', 'required_if:ai_provider,yandexgpt', 'string', 'max:255'],
            'yandexgpt_model' => ['required', 'string', 'max:255'],
            'yandexgpt_embedding_model' => ['required', 'string', 'max:255'],
            'yandexgpt_api_key' => ['nullable', 'string', 'max:4096'],
            'yandexgpt_iam_token' => ['nullable', 'string', 'max:4096'],
            'yandexgpt_timeout' => ['required', 'integer', 'min:1', 'max:600'],
            'yandexgpt_connect_timeout' => [
                'required',
                'integer',
                'min:1',
                'max:120',
                'lte:yandexgpt_timeout',
            ],
            'yandexgpt_max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'yandexgpt_verify_ssl' => ['accepted'],
            'clear_yandexgpt_credentials' => ['boolean'],
            'openai_api_url' => [
                'required',
                'url',
                'starts_with:https://',
                'max:2048',
                $this->officialEndpoint('api.openai.com', 443),
            ],
            'openai_model' => ['required', 'string', 'max:255'],
            'openai_embedding_model' => ['required', 'string', 'max:255'],
            'openai_organization' => ['nullable', 'string', 'max:255'],
            'openai_project' => ['nullable', 'string', 'max:255'],
            'openai_api_key' => ['nullable', 'string', 'max:4096'],
            'openai_timeout' => ['required', 'integer', 'min:1', 'max:600'],
            'openai_connect_timeout' => [
                'required',
                'integer',
                'min:1',
                'max:120',
                'lte:openai_timeout',
            ],
            'openai_max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'openai_verify_ssl' => ['accepted'],
            'clear_openai_credentials' => ['boolean'],
            'gemini_api_url' => [
                'required',
                'url',
                'starts_with:https://',
                'max:2048',
                $this->officialEndpoint('generativelanguage.googleapis.com', 443),
            ],
            'gemini_model' => ['required', 'string', 'max:255', 'regex:/^(?:models\/)?[a-zA-Z0-9._-]+$/'],
            'gemini_embedding_model' => [
                'required',
                'string',
                'max:255',
                'regex:/^(?:models\/)?[a-zA-Z0-9._-]+$/',
            ],
            'gemini_api_key' => ['nullable', 'string', 'max:4096'],
            'gemini_timeout' => ['required', 'integer', 'min:1', 'max:600'],
            'gemini_connect_timeout' => [
                'required',
                'integer',
                'min:1',
                'max:120',
                'lte:gemini_timeout',
            ],
            'gemini_max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'gemini_verify_ssl' => ['accepted'],
            'clear_gemini_credentials' => ['boolean'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $newClientId = trim((string) $this->input('gigachat_client_id'));
            $newClientSecret = trim((string) $this->input('gigachat_client_secret'));
            if (($newClientId === '') !== ($newClientSecret === '')) {
                $validator->errors()->add(
                    $newClientId === '' ? 'gigachat_client_id' : 'gigachat_client_secret',
                    'Client ID и Client Secret необходимо заменять одновременно.',
                );

                return;
            }

            $provider = (string) $this->input('ai_provider');
            $stored = app(AISettings::class)->adminValues();

            if ($provider === 'gigachat') {
                if ($this->boolean('clear_gigachat_secrets')) {
                    $validator->errors()->add(
                        'clear_gigachat_secrets',
                        'Перед удалением учётных данных переключите активный провайдер.',
                    );

                    return;
                }

                $hasAuthKey = trim((string) $this->input('gigachat_auth_key')) !== ''
                    || $stored['gigachat']['auth_key_configured'];
                $hasClientId = trim((string) $this->input('gigachat_client_id')) !== ''
                    || $stored['gigachat']['client_id_configured'];
                $hasClientSecret = trim((string) $this->input('gigachat_client_secret')) !== ''
                    || $stored['gigachat']['client_secret_configured'];

                if (! $hasAuthKey && ! ($hasClientId && $hasClientSecret)) {
                    $validator->errors()->add(
                        'gigachat_auth_key',
                        'Для GigaChat укажите Authorization Key или пару Client ID / Client Secret.',
                    );
                }

                return;
            }

            if ($provider === 'yandexgpt') {
                if ($this->boolean('clear_yandexgpt_credentials')) {
                    $validator->errors()->add(
                        'clear_yandexgpt_credentials',
                        'Перед удалением учётных данных переключите активный провайдер.',
                    );

                    return;
                }

                $hasApiKey = trim((string) $this->input('yandexgpt_api_key')) !== ''
                    || $stored['yandexgpt']['api_key_configured'];
                $hasIamToken = trim((string) $this->input('yandexgpt_iam_token')) !== ''
                    || $stored['yandexgpt']['iam_token_configured'];
                if (! $hasApiKey && ! $hasIamToken) {
                    $validator->errors()->add(
                        'yandexgpt_api_key',
                        'Для YandexGPT укажите API Key или IAM-токен.',
                    );
                }

                return;
            }

            if ($provider === 'openai') {
                if ($this->boolean('clear_openai_credentials')) {
                    $validator->errors()->add(
                        'clear_openai_credentials',
                        'Перед удалением учётных данных переключите активный провайдер.',
                    );

                    return;
                }

                $hasApiKey = trim((string) $this->input('openai_api_key')) !== ''
                    || $stored['openai']['api_key_configured'];
                if (! $hasApiKey) {
                    $validator->errors()->add('openai_api_key', 'Для OpenAI укажите API Key.');
                }

                return;
            }

            if ($provider === 'gemini') {
                if ($this->boolean('clear_gemini_credentials')) {
                    $validator->errors()->add(
                        'clear_gemini_credentials',
                        'Перед удалением учётных данных переключите активный провайдер.',
                    );

                    return;
                }

                $hasApiKey = trim((string) $this->input('gemini_api_key')) !== ''
                    || $stored['gemini']['api_key_configured'];
                if (! $hasApiKey) {
                    $validator->errors()->add('gemini_api_key', 'Для Gemini укажите API Key.');
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'automatic_publication' => $this->boolean('automatic_publication'),
            'gigachat_embedding_fallback' => $this->boolean('gigachat_embedding_fallback'),
            'gigachat_verify_ssl' => $this->boolean('gigachat_verify_ssl'),
            'clear_gigachat_secrets' => $this->boolean('clear_gigachat_secrets'),
            'yandexgpt_verify_ssl' => $this->boolean('yandexgpt_verify_ssl'),
            'clear_yandexgpt_credentials' => $this->boolean('clear_yandexgpt_credentials'),
            'openai_verify_ssl' => $this->boolean('openai_verify_ssl'),
            'clear_openai_credentials' => $this->boolean('clear_openai_credentials'),
            'gemini_verify_ssl' => $this->boolean('gemini_verify_ssl'),
            'clear_gemini_credentials' => $this->boolean('clear_gemini_credentials'),
            'event_similarity_threshold' => str_replace(
                ',',
                '.',
                (string) $this->input('event_similarity_threshold'),
            ),
        ]);
    }

    private function officialEndpoint(string $host, int $port): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($host, $port): void {
            if (! is_string($value)) {
                return;
            }

            $parts = parse_url($value);
            if (! is_array($parts)) {
                return;
            }

            $actualHost = strtolower((string) ($parts['host'] ?? ''));
            $actualPort = (int) ($parts['port'] ?? 443);
            $hasUserInfo = isset($parts['user']) || isset($parts['pass']);

            if ($actualHost !== $host || $actualPort !== $port || $hasUserInfo) {
                $fail("Разрешён только официальный адрес {$host}:{$port}.");
            }
        };
    }
}
