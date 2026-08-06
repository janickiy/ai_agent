<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\DTO\Settings\AgentSettingsData;
use App\DTO\Settings\AISettingsData;
use App\Http\Controllers\Controller;
use App\Modules\Admin\Requests\SettingsRequest;
use App\Modules\NewsMonitor\Models\SystemSetting;
use App\Modules\NewsMonitor\Services\AgentSettings;
use App\Modules\NewsMonitor\Services\AISettings;
use App\Modules\NewsMonitor\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class SettingsController extends Controller
{
    /**
     * Отображает единую форму настроек агента и подключений AI-провайдеров.
     *
     * В представление передаются публичные значения, признаки сохранённых секретов
     * и полный список провайдеров без раскрытия конфиденциальных реквизитов.
     */
    public function edit(AgentSettings $settings, AISettings $aiSettings): View
    {
        return view('admin.settings.edit', [
            'settings' => $settings->all(),
            'aiSettings' => $aiSettings->adminValues(),
            'aiProviderOptions' => AISettings::providerOptions(),
        ]);
    }

    /**
     * Сохраняет общие настройки агента, параметры AI-провайдеров и изменения реквизитов доступа.
     *
     * Метод преобразует валидированные данные в DTO, выполняет обновление транзакционно
     * и фиксирует безопасные снимки настроек в аудите без записи секретов открытым текстом.
     *
     * @throws \Throwable
     */
    public function update(
        SettingsRequest $request,
        AgentSettings $settings,
        AISettings $aiSettings,
        AuditLogger $audit,
    ): RedirectResponse {
        $before = [
            'agent' => $settings->all(),
            'ai' => $aiSettings->auditSnapshot(),
        ];
        $validated = $request->validated();
        $agentData = AgentSettingsData::fromArray([
            'automatic_publication' => (bool) $validated['automatic_publication'],
            'max_news_age_hours' => (int) $validated['max_news_age_hours'],
            'event_similarity_threshold' => (float) $validated['event_similarity_threshold'],
        ]);
        $gigachat = [
            'auth_url' => $validated['gigachat_auth_url'],
            'api_url' => $validated['gigachat_api_url'],
            'scope' => $validated['gigachat_scope'],
            'model' => $validated['gigachat_model'],
            'embedding_model' => $validated['gigachat_embedding_model'],
            'embedding_fallback' => (bool) $validated['gigachat_embedding_fallback'],
            'timeout' => (int) $validated['gigachat_timeout'],
            'connect_timeout' => (int) $validated['gigachat_connect_timeout'],
            'max_attempts' => (int) $validated['gigachat_max_attempts'],
            'verify_ssl' => (bool) $validated['gigachat_verify_ssl'],
        ];
        $yandexgpt = [
            'api_url' => $validated['yandexgpt_api_url'],
            'folder_id' => $validated['yandexgpt_folder_id'] ?? '',
            'model' => $validated['yandexgpt_model'],
            'embedding_model' => $validated['yandexgpt_embedding_model'],
            'timeout' => (int) $validated['yandexgpt_timeout'],
            'connect_timeout' => (int) $validated['yandexgpt_connect_timeout'],
            'max_attempts' => (int) $validated['yandexgpt_max_attempts'],
            'verify_ssl' => (bool) $validated['yandexgpt_verify_ssl'],
        ];
        $openai = [
            'api_url' => $validated['openai_api_url'],
            'model' => $validated['openai_model'],
            'embedding_model' => $validated['openai_embedding_model'],
            'organization' => $validated['openai_organization'] ?? '',
            'project' => $validated['openai_project'] ?? '',
            'timeout' => (int) $validated['openai_timeout'],
            'connect_timeout' => (int) $validated['openai_connect_timeout'],
            'max_attempts' => (int) $validated['openai_max_attempts'],
            'verify_ssl' => (bool) $validated['openai_verify_ssl'],
        ];
        $gemini = [
            'api_url' => $validated['gemini_api_url'],
            'model' => $validated['gemini_model'],
            'embedding_model' => $validated['gemini_embedding_model'],
            'timeout' => (int) $validated['gemini_timeout'],
            'connect_timeout' => (int) $validated['gemini_connect_timeout'],
            'max_attempts' => (int) $validated['gemini_max_attempts'],
            'verify_ssl' => (bool) $validated['gemini_verify_ssl'],
        ];
        $providerCredentials = [
            'gigachat' => [
                'auth_key' => $validated['gigachat_auth_key'] ?? null,
                'client_id' => $validated['gigachat_client_id'] ?? null,
                'client_secret' => $validated['gigachat_client_secret'] ?? null,
            ],
            'yandexgpt' => [
                'api_key' => $validated['yandexgpt_api_key'] ?? null,
                'iam_token' => $validated['yandexgpt_iam_token'] ?? null,
            ],
            'openai' => [
                'api_key' => $validated['openai_api_key'] ?? null,
            ],
            'gemini' => [
                'api_key' => $validated['gemini_api_key'] ?? null,
            ],
        ];
        $clearCredentials = [
            'gigachat' => (bool) $validated['clear_gigachat_secrets'],
            'yandexgpt' => (bool) $validated['clear_yandexgpt_credentials'],
            'openai' => (bool) $validated['clear_openai_credentials'],
            'gemini' => (bool) $validated['clear_gemini_credentials'],
        ];
        $aiData = AISettingsData::fromArray([
            'provider' => $validated['ai_provider'],
            'provider_settings' => [
                'gigachat' => $gigachat,
                'yandexgpt' => $yandexgpt,
                'openai' => $openai,
                'gemini' => $gemini,
            ],
            'provider_credentials' => $providerCredentials,
            'clear_credentials' => $clearCredentials,
        ]);

        DB::transaction(function () use (
            $request,
            $settings,
            $aiSettings,
            $audit,
            $before,
            $agentData,
            $aiData,
        ): void {
            $setting = $settings->update($agentData);
            $aiSettings->update($aiData);

            $audit->record(
                $request->user()->id,
                'settings.updated',
                SystemSetting::class,
                $setting->getKey(),
                $before,
                [
                    'agent' => $settings->all(),
                    'ai' => $aiSettings->auditSnapshot(),
                ],
            );
        });

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', 'Настройки сохранены.')
            ->with('settings_tab', $validated['settings_tab']);
    }
}
