<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTO\Settings\AISettingsData;
use App\Models\User;
use App\Modules\NewsMonitor\AI\Contracts\AIProvider;
use App\Modules\NewsMonitor\AI\Providers\GeminiProvider;
use App\Modules\NewsMonitor\AI\Providers\GigaChatProvider;
use App\Modules\NewsMonitor\AI\Providers\OpenAIProvider;
use App\Modules\NewsMonitor\AI\Providers\RuleBasedAIProvider;
use App\Modules\NewsMonitor\AI\Providers\YandexGPTProvider;
use App\Modules\NewsMonitor\Models\AuditLog;
use App\Modules\NewsMonitor\Models\SystemSetting;
use App\Modules\NewsMonitor\Services\AgentSettings;
use App\Modules\NewsMonitor\Services\AISettings;
use App\Modules\NewsMonitor\Services\KaboomSettings;
use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class SettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_contains_separate_provider_connection_tabs(): void
    {
        $this->actingAs($this->administrator())
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Настройки агента')
            ->assertSee('Локальные правила')
            ->assertDontSee('Адаптер не подключён')
            ->assertSee('Адаптер подключён')
            ->assertSee('id="gigachat-settings-tab"', false)
            ->assertSee('id="yandexgpt-settings-tab"', false)
            ->assertSee('id="openai-settings-tab"', false)
            ->assertSee('id="gemini-settings-tab"', false)
            ->assertSee('id="gigachat-settings-pane"', false)
            ->assertSee('id="yandexgpt-settings-pane"', false)
            ->assertSee('id="openai-settings-pane"', false)
            ->assertSee('id="gemini-settings-pane"', false)
            ->assertSee('Подключение GigaChat')
            ->assertSee('Подключение YandexGPT')
            ->assertSee('Подключение OpenAI')
            ->assertSee('Подключение Google Gemini')
            ->assertSee('Публикация на Kaboom')
            ->assertSee(KaboomSettings::ENDPOINT)
            ->assertSee('X-API-Key')
            ->assertSee('Автоматическое создание публикаций')
            ->assertSee('Сохранить');
    }

    public function test_administrator_can_store_encrypted_kaboom_api_key_without_exposing_it(): void
    {
        $administrator = $this->administrator();
        $apiKey = 'plain-kaboom-api-key-for-test';

        $this->actingAs($administrator)
            ->put('/admin/settings', $this->payload([
                'gigachat_auth_key' => 'valid-gigachat-key',
                'kaboom_api_key' => $apiKey,
            ]))
            ->assertRedirect(route('admin.settings.edit'));

        $setting = SystemSetting::query()->findOrFail('publishing.kaboom.credentials');
        self::assertTrue($setting->is_secret);
        self::assertArrayHasKey('api_key', $setting->value);
        self::assertNotSame($apiKey, $setting->value['api_key']);
        self::assertStringNotContainsString(
            $apiKey,
            $this->rawSettingValue('publishing.kaboom.credentials'),
        );
        self::assertSame(KaboomSettings::ENDPOINT, app(KaboomSettings::class)->endpoint());
        self::assertSame($apiKey, app(KaboomSettings::class)->apiKey());

        $auditJson = AuditLog::query()->where('action', 'settings.updated')->firstOrFail()->toJson();
        self::assertStringNotContainsString($apiKey, $auditJson);

        $this->actingAs($administrator)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee(KaboomSettings::ENDPOINT)
            ->assertSee('Сохранён')
            ->assertDontSee($apiKey);
    }

    public function test_empty_kaboom_key_keeps_existing_value_and_clear_checkbox_deletes_it(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->put('/admin/settings', $this->payload([
                'gigachat_auth_key' => 'valid-gigachat-key',
                'kaboom_api_key' => 'existing-kaboom-api-key',
            ]))
            ->assertRedirect();

        $this->actingAs($administrator)
            ->put('/admin/settings', $this->payload())
            ->assertRedirect();

        self::assertSame('existing-kaboom-api-key', app(KaboomSettings::class)->apiKey());

        $this->actingAs($administrator)
            ->put('/admin/settings', $this->payload([
                'clear_kaboom_api_key' => '1',
                'kaboom_api_key' => 'must-not-be-stored',
            ]))
            ->assertRedirect();

        self::assertNull(SystemSetting::query()->find('publishing.kaboom.credentials'));
        self::assertSame('', app(KaboomSettings::class)->apiKey());
    }

    public function test_corrupted_kaboom_api_key_is_reported_without_being_rendered(): void
    {
        $administrator = $this->administrator();
        $this->actingAs($administrator)
            ->put('/admin/settings', $this->payload([
                'gigachat_auth_key' => 'valid-gigachat-key',
                'kaboom_api_key' => 'kaboom-key-before-corruption',
            ]))
            ->assertRedirect();
        DB::table(NewsTables::name('settings'))
            ->where('key', 'publishing.kaboom.credentials')
            ->update(['value' => json_encode([
                'api_key' => 'not-a-valid-encrypted-kaboom-key',
            ], JSON_THROW_ON_ERROR)]);

        $this->actingAs($administrator)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Не удалось расшифровать сохранённый API-ключ Kaboom')
            ->assertDontSee('not-a-valid-encrypted-kaboom-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Не удалось расшифровать API-ключ Kaboom');
        app(KaboomSettings::class)->apiKey();
    }

    public function test_administrator_can_store_all_provider_settings_and_encrypted_credentials(): void
    {
        $administrator = $this->administrator();
        $authKey = 'plain-auth-key-for-test';
        $clientId = 'plain-client-id-for-test';
        $clientSecret = 'plain-client-secret-for-test';
        $yandexApiKey = 'plain-yandex-api-key-for-test';
        $yandexIamToken = 'plain-yandex-iam-token-for-test';
        $openAiApiKey = 'plain-openai-api-key-for-test';
        $geminiApiKey = 'plain-gemini-api-key-for-test';

        $this->actingAs($administrator)
            ->put('/admin/settings', $this->payload([
                'settings_tab' => 'openai',
                'gigachat_auth_key' => $authKey,
                'gigachat_client_id' => $clientId,
                'gigachat_client_secret' => $clientSecret,
                'yandexgpt_folder_id' => 'folder-for-test',
                'yandexgpt_model' => 'yandexgpt-test/latest',
                'yandexgpt_embedding_model' => 'text-embedding-test/latest',
                'yandexgpt_api_key' => $yandexApiKey,
                'yandexgpt_iam_token' => $yandexIamToken,
                'openai_model' => 'openai-model-for-test',
                'openai_embedding_model' => 'openai-embedding-for-test',
                'openai_organization' => 'org-for-test',
                'openai_project' => 'project-for-test',
                'openai_api_key' => $openAiApiKey,
                'gemini_model' => 'gemini-test-model',
                'gemini_embedding_model' => 'gemini-embedding-test',
                'gemini_api_key' => $geminiApiKey,
            ]))
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('status', 'Настройки сохранены.')
            ->assertSessionHas('settings_tab', 'openai');

        $agent = SystemSetting::query()->findOrFail('agent');
        self::assertSame([
            'automatic_publication' => true,
            'max_news_age_hours' => 72,
            'event_similarity_threshold' => 0.72,
        ], $agent->value);

        $ai = SystemSetting::query()->findOrFail('ai');
        self::assertSame('gigachat', $ai->value['provider']);
        self::assertSame('GigaChat-Test', $ai->value['gigachat']['model']);
        self::assertSame('folder-for-test', $ai->value['yandexgpt']['folder_id']);
        self::assertSame('yandexgpt-test/latest', $ai->value['yandexgpt']['model']);
        self::assertSame('text-embedding-test/latest', $ai->value['yandexgpt']['embedding_model']);
        self::assertSame('openai-model-for-test', $ai->value['openai']['model']);
        self::assertSame('openai-embedding-for-test', $ai->value['openai']['embedding_model']);
        self::assertSame('org-for-test', $ai->value['openai']['organization']);
        self::assertSame('project-for-test', $ai->value['openai']['project']);
        self::assertSame('gemini-test-model', $ai->value['gemini']['model']);
        self::assertSame('gemini-embedding-test', $ai->value['gemini']['embedding_model']);
        self::assertFalse($ai->is_secret);

        foreach (['ai.gigachat.credentials', 'ai.yandexgpt.credentials', 'ai.openai.credentials', 'ai.gemini.credentials'] as $key) {
            self::assertTrue(SystemSetting::query()->findOrFail($key)->is_secret);
        }

        $rawGigachatCredentials = $this->rawSettingValue('ai.gigachat.credentials');
        self::assertStringNotContainsString($authKey, $rawGigachatCredentials);
        self::assertStringNotContainsString($clientId, $rawGigachatCredentials);
        self::assertStringNotContainsString($clientSecret, $rawGigachatCredentials);

        $rawYandexCredentials = $this->rawSettingValue('ai.yandexgpt.credentials');
        self::assertStringNotContainsString($yandexApiKey, $rawYandexCredentials);
        self::assertStringNotContainsString($yandexIamToken, $rawYandexCredentials);

        $rawOpenAiCredentials = $this->rawSettingValue('ai.openai.credentials');
        self::assertStringNotContainsString($openAiApiKey, $rawOpenAiCredentials);

        $rawGeminiCredentials = $this->rawSettingValue('ai.gemini.credentials');
        self::assertStringNotContainsString($geminiApiKey, $rawGeminiCredentials);

        $config = app(AISettings::class)->gigachatConfig();
        self::assertSame($authKey, $config['auth_key']);
        self::assertSame($clientId, $config['client_id']);
        self::assertSame($clientSecret, $config['client_secret']);
        self::assertSame('GigaChat-Test', $config['model']);

        $yandexConfig = $this->providerSettings('yandexgpt');
        self::assertSame($yandexApiKey, $yandexConfig['api_key']);
        self::assertSame($yandexIamToken, $yandexConfig['iam_token']);
        self::assertSame('folder-for-test', $yandexConfig['folder_id']);
        self::assertSame('yandexgpt-test/latest', $yandexConfig['model']);
        self::assertSame('text-embedding-test/latest', $yandexConfig['embedding_model']);

        $openAiConfig = $this->providerSettings('openai');
        self::assertSame($openAiApiKey, $openAiConfig['api_key']);
        self::assertSame('openai-model-for-test', $openAiConfig['model']);
        self::assertSame('openai-embedding-for-test', $openAiConfig['embedding_model']);
        self::assertSame('org-for-test', $openAiConfig['organization']);
        self::assertSame('project-for-test', $openAiConfig['project']);

        $geminiConfig = $this->providerSettings('gemini');
        self::assertSame($geminiApiKey, $geminiConfig['api_key']);
        self::assertSame('gemini-test-model', $geminiConfig['model']);
        self::assertSame('gemini-embedding-test', $geminiConfig['embedding_model']);

        $auditJson = AuditLog::query()->where('action', 'settings.updated')->firstOrFail()->toJson();
        foreach ([$authKey, $clientId, $clientSecret, $yandexApiKey, $yandexIamToken, $openAiApiKey, $geminiApiKey] as $secret) {
            self::assertStringNotContainsString($secret, $auditJson);
        }

        $response = $this->actingAs($administrator)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Сохранён');
        foreach ([$authKey, $clientId, $clientSecret, $yandexApiKey, $yandexIamToken, $openAiApiKey, $geminiApiKey] as $secret) {
            $response->assertDontSee($secret);
        }

        $settings = app(AgentSettings::class);
        self::assertTrue($settings->automaticPublication());
        self::assertSame(72, $settings->maxNewsAgeHours());
        self::assertSame(0.72, $settings->eventSimilarityThreshold());
    }

    public function test_empty_secret_fields_keep_existing_credentials(): void
    {
        $administrator = $this->administrator();
        $this->actingAs($administrator)->put('/admin/settings', $this->payload([
            'gigachat_auth_key' => 'existing-auth-key',
            'gigachat_client_id' => 'existing-client-id',
            'gigachat_client_secret' => 'existing-client-secret',
            'yandexgpt_api_key' => 'existing-yandex-api-key',
            'yandexgpt_iam_token' => 'existing-yandex-iam-token',
            'openai_api_key' => 'existing-openai-api-key',
            'gemini_api_key' => 'existing-gemini-api-key',
        ]))->assertRedirect();

        $this->actingAs($administrator)
            ->put('/admin/settings', $this->payload([
                'settings_tab' => 'yandexgpt',
                'gigachat_model' => 'GigaChat-New',
                'yandexgpt_model' => 'YandexGPT-New',
                'openai_model' => 'OpenAI-New',
                'gemini_model' => 'Gemini-New',
            ]))
            ->assertRedirect();

        $config = app(AISettings::class)->gigachatConfig();
        self::assertSame('existing-auth-key', $config['auth_key']);
        self::assertSame('existing-client-id', $config['client_id']);
        self::assertSame('existing-client-secret', $config['client_secret']);
        self::assertSame('GigaChat-New', $config['model']);

        $yandexConfig = $this->providerSettings('yandexgpt');
        self::assertSame('existing-yandex-api-key', $yandexConfig['api_key']);
        self::assertSame('existing-yandex-iam-token', $yandexConfig['iam_token']);
        self::assertSame('YandexGPT-New', $yandexConfig['model']);

        $openAiConfig = $this->providerSettings('openai');
        self::assertSame('existing-openai-api-key', $openAiConfig['api_key']);
        self::assertSame('OpenAI-New', $openAiConfig['model']);

        $geminiConfig = $this->providerSettings('gemini');
        self::assertSame('existing-gemini-api-key', $geminiConfig['api_key']);
        self::assertSame('Gemini-New', $geminiConfig['model']);
    }

    public function test_administrator_can_explicitly_clear_all_provider_credentials(): void
    {
        $administrator = $this->administrator();
        $this->actingAs($administrator)->put('/admin/settings', $this->payload([
            'gigachat_auth_key' => 'auth-key-to-remove',
            'gigachat_client_id' => 'client-id-to-remove',
            'gigachat_client_secret' => 'client-secret-to-remove',
            'yandexgpt_api_key' => 'yandex-api-key-to-remove',
            'yandexgpt_iam_token' => 'yandex-iam-token-to-remove',
            'openai_api_key' => 'openai-api-key-to-remove',
            'gemini_api_key' => 'gemini-api-key-to-remove',
        ]))->assertRedirect();

        $this->actingAs($administrator)
            ->put('/admin/settings', $this->payload([
                'ai_provider' => 'rules',
                'clear_gigachat_secrets' => '1',
                'clear_yandexgpt_credentials' => '1',
                'clear_openai_credentials' => '1',
                'clear_gemini_credentials' => '1',
            ]))
            ->assertRedirect();

        $config = app(AISettings::class)->gigachatConfig();
        self::assertSame('', $config['auth_key']);
        self::assertSame('', $config['client_id']);
        self::assertSame('', $config['client_secret']);
        self::assertSame('', $this->providerSettings('yandexgpt')['api_key']);
        self::assertSame('', $this->providerSettings('yandexgpt')['iam_token']);
        self::assertSame('', $this->providerSettings('openai')['api_key']);
        self::assertSame('', $this->providerSettings('gemini')['api_key']);
    }

    public function test_clear_credentials_takes_precedence_over_replacement_values(): void
    {
        $administrator = $this->administrator();
        $this->actingAs($administrator)->put('/admin/settings', $this->payload([
            'gigachat_auth_key' => 'auth-key-to-remove',
            'gigachat_client_id' => 'client-id-to-remove',
            'gigachat_client_secret' => 'client-secret-to-remove',
        ]))->assertRedirect();

        $this->actingAs($administrator)
            ->put('/admin/settings', $this->payload([
                'ai_provider' => 'rules',
                'clear_gigachat_secrets' => '1',
                'gigachat_auth_key' => 'must-not-be-stored',
                'gigachat_client_id' => 'must-not-be-stored',
                'gigachat_client_secret' => 'must-not-be-stored',
            ]))
            ->assertRedirect();

        $config = app(AISettings::class)->gigachatConfig();
        self::assertSame('', $config['auth_key']);
        self::assertSame('', $config['client_id']);
        self::assertSame('', $config['client_secret']);
    }

    public function test_gigachat_cannot_be_activated_without_working_credentials(): void
    {
        $this->actingAs($this->administrator())
            ->from('/admin/settings')
            ->put('/admin/settings', $this->payload())
            ->assertRedirect('/admin/settings')
            ->assertSessionHasErrors('gigachat_auth_key');

        self::assertSame('rules', app(AISettings::class)->provider());
    }

    public function test_yandexgpt_cannot_be_activated_without_working_credentials(): void
    {
        $this->actingAs($this->administrator())
            ->from('/admin/settings')
            ->put('/admin/settings', $this->payload(['ai_provider' => 'yandexgpt']))
            ->assertRedirect('/admin/settings')
            ->assertSessionHasErrors('yandexgpt_api_key');

        self::assertSame('rules', app(AISettings::class)->provider());
    }

    public function test_openai_cannot_be_activated_without_working_credentials(): void
    {
        $this->actingAs($this->administrator())
            ->from('/admin/settings')
            ->put('/admin/settings', $this->payload(['ai_provider' => 'openai']))
            ->assertRedirect('/admin/settings')
            ->assertSessionHasErrors('openai_api_key');

        self::assertSame('rules', app(AISettings::class)->provider());
    }

    public function test_gemini_cannot_be_activated_without_api_key(): void
    {
        $this->actingAs($this->administrator())
            ->from('/admin/settings')
            ->put('/admin/settings', $this->payload([
                'ai_provider' => 'gemini',
                'settings_tab' => 'gemini',
            ]))
            ->assertRedirect('/admin/settings')
            ->assertSessionHasErrors('gemini_api_key');

        self::assertSame('rules', app(AISettings::class)->provider());
    }

    public function test_gigachat_credentials_cannot_be_sent_to_an_untrusted_endpoint(): void
    {
        $administrator = $this->administrator();
        $this->actingAs($administrator)
            ->put('/admin/settings', $this->payload([
                'gigachat_auth_key' => 'credential-that-must-stay-local',
            ]))
            ->assertRedirect();

        $this->actingAs($administrator)
            ->from('/admin/settings')
            ->put('/admin/settings', $this->payload([
                'gigachat_auth_url' => 'https://attacker.example.test/oauth',
            ]))
            ->assertRedirect('/admin/settings')
            ->assertSessionHasErrors('gigachat_auth_url');

        self::assertSame(
            'credential-that-must-stay-local',
            app(AISettings::class)->gigachatConfig()['auth_key'],
        );
    }

    public function test_yandexgpt_and_openai_credentials_cannot_be_sent_to_untrusted_endpoints(): void
    {
        $administrator = $this->administrator();
        $this->actingAs($administrator)
            ->put('/admin/settings', $this->payload([
                'gigachat_auth_key' => 'valid-gigachat-auth-key',
                'yandexgpt_api_key' => 'original-yandex-api-key',
                'yandexgpt_iam_token' => 'original-yandex-iam-token',
                'openai_api_key' => 'original-openai-api-key',
            ]))
            ->assertRedirect();

        $this->actingAs($administrator)
            ->from('/admin/settings')
            ->put('/admin/settings', $this->payload([
                'settings_tab' => 'yandexgpt',
                'yandexgpt_api_url' => 'https://attacker.example.test/v1',
                'yandexgpt_api_key' => 'replacement-yandex-key-must-not-be-stored',
            ]))
            ->assertRedirect('/admin/settings')
            ->assertSessionHasErrors('yandexgpt_api_url');

        $yandexConfig = $this->providerSettings('yandexgpt');
        self::assertSame('original-yandex-api-key', $yandexConfig['api_key']);
        self::assertSame('original-yandex-iam-token', $yandexConfig['iam_token']);

        $this->actingAs($administrator)
            ->from('/admin/settings')
            ->put('/admin/settings', $this->payload([
                'settings_tab' => 'openai',
                'openai_api_url' => 'https://attacker.example.test/v1',
                'openai_api_key' => 'replacement-openai-key-must-not-be-stored',
            ]))
            ->assertRedirect('/admin/settings')
            ->assertSessionHasErrors('openai_api_url');

        self::assertSame(
            'original-openai-api-key',
            $this->providerSettings('openai')['api_key'],
        );
    }

    public function test_gemini_credentials_cannot_be_sent_to_an_untrusted_endpoint(): void
    {
        $administrator = $this->administrator();
        $this->actingAs($administrator)
            ->put('/admin/settings', $this->payload([
                'gigachat_auth_key' => 'valid-gigachat-auth-key',
                'gemini_api_key' => 'original-gemini-api-key',
            ]))
            ->assertRedirect();

        $this->actingAs($administrator)
            ->from('/admin/settings')
            ->put('/admin/settings', $this->payload([
                'settings_tab' => 'gemini',
                'gemini_api_url' => 'https://attacker.example.test/v1beta',
                'gemini_api_key' => 'replacement-gemini-key-must-not-be-stored',
            ]))
            ->assertRedirect('/admin/settings')
            ->assertSessionHasErrors('gemini_api_url');

        self::assertSame(
            'original-gemini-api-key',
            $this->providerSettings('gemini')['api_key'],
        );
    }

    public function test_client_id_and_client_secret_are_replaced_only_as_a_pair(): void
    {
        $administrator = $this->administrator();
        $this->actingAs($administrator)
            ->put('/admin/settings', $this->payload([
                'gigachat_client_id' => 'original-client-id',
                'gigachat_client_secret' => 'original-client-secret',
            ]))
            ->assertRedirect();

        $this->actingAs($administrator)
            ->from('/admin/settings')
            ->put('/admin/settings', $this->payload([
                'gigachat_client_id' => 'new-client-id',
            ]))
            ->assertRedirect('/admin/settings')
            ->assertSessionHasErrors('gigachat_client_secret');

        $config = app(AISettings::class)->gigachatConfig();
        self::assertSame('original-client-id', $config['client_id']);
        self::assertSame('original-client-secret', $config['client_secret']);
    }

    public function test_tls_verification_cannot_be_disabled_for_gigachat(): void
    {
        $this->actingAs($this->administrator())
            ->from('/admin/settings')
            ->put('/admin/settings', $this->payload([
                'gigachat_auth_key' => 'valid-auth-key',
                'gigachat_verify_ssl' => '0',
            ]))
            ->assertRedirect('/admin/settings')
            ->assertSessionHasErrors('gigachat_verify_ssl');
    }

    public function test_corrupted_credentials_are_reported_instead_of_silently_overwritten(): void
    {
        app(AISettings::class)->update($this->gigachatSettingsData([
            'auth_key' => 'encrypted-before-corruption',
        ]));
        DB::table(NewsTables::name('settings'))
            ->where('key', 'ai.gigachat.credentials')
            ->update(['value' => json_encode([
                'auth_key' => 'not-a-valid-encrypted-value',
                'client_id' => null,
                'client_secret' => null,
            ], JSON_THROW_ON_ERROR)]);

        $this->expectException(RuntimeException::class);
        app(AISettings::class)->gigachatConfig();
    }

    public function test_administrator_can_recover_corrupted_credentials_from_settings(): void
    {
        app(AISettings::class)->update($this->gigachatSettingsData([
            'auth_key' => 'encrypted-before-corruption',
        ]));
        DB::table(NewsTables::name('settings'))
            ->where('key', 'ai.gigachat.credentials')
            ->update(['value' => json_encode([
                'auth_key' => 'not-a-valid-encrypted-value',
                'client_id' => null,
                'client_secret' => null,
            ], JSON_THROW_ON_ERROR)]);
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Не удалось расшифровать сохранённые учётные данные');

        $this->actingAs($administrator)
            ->put('/admin/settings', $this->payload([
                'gigachat_auth_key' => 'replacement-after-corruption',
            ]))
            ->assertRedirect();

        self::assertSame(
            'replacement-after-corruption',
            app(AISettings::class)->gigachatConfig()['auth_key'],
        );
        self::assertFalse(
            app(AISettings::class)->adminValues()['gigachat']['credentials_decryption_error'],
        );
    }

    public function test_secret_values_are_not_flashed_after_validation_error(): void
    {
        $administrator = $this->administrator();
        $secrets = [
            'gigachat_auth_key' => 'must-not-be-flashed',
            'gigachat_client_id' => 'must-not-be-flashed-client',
            'gigachat_client_secret' => 'must-not-be-flashed-secret',
            'yandexgpt_api_key' => 'must-not-be-flashed-yandex-api-key',
            'yandexgpt_iam_token' => 'must-not-be-flashed-yandex-iam-token',
            'openai_api_key' => 'must-not-be-flashed-openai-api-key',
            'gemini_api_key' => 'must-not-be-flashed-gemini-api-key',
            'kaboom_api_key' => 'must-not-be-flashed-kaboom-api-key',
        ];

        $response = $this->actingAs($administrator)
            ->from('/admin/settings')
            ->put('/admin/settings', $this->payload(array_replace($secrets, [
                'settings_tab' => 'yandexgpt',
                'gigachat_auth_url' => 'not-a-url',
            ])))
            ->assertRedirect('/admin/settings')
            ->assertSessionHasErrors('gigachat_auth_url');

        foreach (array_keys($secrets) as $field) {
            $response->assertSessionMissing("_old_input.{$field}");
        }

        $settingsPage = $this->actingAs($administrator)
            ->get('/admin/settings')
            ->assertOk();
        foreach ($secrets as $secret) {
            $settingsPage->assertDontSee($secret);
        }
    }

    public function test_ai_provider_is_resolved_from_database_for_each_resolution(): void
    {
        self::assertInstanceOf(RuleBasedAIProvider::class, app(AIProvider::class));

        app(AISettings::class)->update($this->gigachatSettingsData([
            'auth_key' => 'database-auth-key',
        ]));

        self::assertInstanceOf(GigaChatProvider::class, app(AIProvider::class));

        app(AISettings::class)->update($this->providerSettingsData(
            'yandexgpt',
            ['api_key' => 'database-yandex-key'],
            ['folder_id' => 'database-folder'],
        ));

        self::assertInstanceOf(YandexGPTProvider::class, app(AIProvider::class));

        app(AISettings::class)->update($this->providerSettingsData(
            'openai',
            ['api_key' => 'database-openai-key'],
        ));

        self::assertInstanceOf(OpenAIProvider::class, app(AIProvider::class));

        app(AISettings::class)->update($this->providerSettingsData(
            'gemini',
            ['api_key' => 'database-gemini-key'],
        ));

        self::assertInstanceOf(GeminiProvider::class, app(AIProvider::class));
    }

    public function test_viewer_can_see_settings_but_cannot_update_them_or_see_secrets(): void
    {
        app(AISettings::class)->update($this->gigachatSettingsData([
            'auth_key' => 'viewer-must-not-see-this',
        ]));
        $viewer = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
            'admin_access' => true,
        ]);

        $this->actingAs($viewer)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Настройки агента')
            ->assertSee('Сохранён')
            ->assertDontSee('viewer-must-not-see-this')
            ->assertDontSee('Сохранить');

        $this->actingAs($viewer)
            ->put('/admin/settings', $this->payload())
            ->assertForbidden();
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'automatic_publication' => '1',
            'max_news_age_hours' => 72,
            'event_similarity_threshold' => '0,72',
            'ai_provider' => 'gigachat',
            'settings_tab' => 'gigachat',
            'gigachat_auth_url' => 'https://ngw.devices.sberbank.ru:9443/api/v2/oauth',
            'gigachat_api_url' => 'https://api.giga.chat/v1',
            'gigachat_scope' => 'GIGACHAT_API_TEST',
            'gigachat_model' => 'GigaChat-Test',
            'gigachat_embedding_model' => 'Embeddings-Test',
            'gigachat_embedding_fallback' => '1',
            'gigachat_timeout' => 90,
            'gigachat_connect_timeout' => 5,
            'gigachat_max_attempts' => 3,
            'gigachat_verify_ssl' => '1',
            'yandexgpt_api_url' => 'https://ai.api.cloud.yandex.net/v1',
            'yandexgpt_folder_id' => 'test-folder-id',
            'yandexgpt_model' => 'yandexgpt/latest',
            'yandexgpt_embedding_model' => 'text-search-doc/latest',
            'yandexgpt_timeout' => 100,
            'yandexgpt_connect_timeout' => 6,
            'yandexgpt_max_attempts' => 4,
            'yandexgpt_verify_ssl' => '1',
            'openai_api_url' => 'https://api.openai.com/v1',
            'openai_model' => 'openai-test-model',
            'openai_embedding_model' => 'text-embedding-3-small',
            'openai_organization' => 'test-organization',
            'openai_project' => 'test-project',
            'openai_timeout' => 110,
            'openai_connect_timeout' => 7,
            'openai_max_attempts' => 5,
            'openai_verify_ssl' => '1',
            'gemini_api_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'gemini_model' => 'gemini-3.6-flash',
            'gemini_embedding_model' => 'gemini-embedding-2',
            'gemini_timeout' => 120,
            'gemini_connect_timeout' => 8,
            'gemini_max_attempts' => 5,
            'gemini_verify_ssl' => '1',
        ], $overrides);
    }

    private function rawSettingValue(string $key): string
    {
        return (string) DB::table(NewsTables::name('settings'))
            ->where('key', $key)
            ->value('value');
    }

    /** @return array<string, mixed> */
    private function providerSettings(string $provider): array
    {
        $public = SystemSetting::query()->findOrFail('ai')->value[$provider] ?? [];
        $storedCredentials = SystemSetting::query()
            ->findOrFail("ai.{$provider}.credentials")
            ->value;
        $credentials = array_map(
            static fn (?string $value): string => $value === null ? '' : Crypt::decryptString($value),
            $storedCredentials,
        );

        return [...$public, ...$credentials];
    }

    /** @param array<string, mixed> $credentials */
    private function gigachatSettingsData(array $credentials): AISettingsData
    {
        return $this->providerSettingsData('gigachat', $credentials);
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $settings
     */
    private function providerSettingsData(
        string $provider,
        array $credentials,
        array $settings = [],
    ): AISettingsData {
        $providerSettings = [
            'gigachat' => (array) config('ai.providers.gigachat'),
            'yandexgpt' => (array) config('ai.providers.yandexgpt'),
            'openai' => (array) config('ai.providers.openai'),
            'gemini' => (array) config('ai.providers.gemini'),
        ];
        $providerSettings[$provider] = array_replace($providerSettings[$provider], $settings);

        return AISettingsData::fromArray([
            'provider' => $provider,
            'provider_settings' => $providerSettings,
            'provider_credentials' => [$provider => $credentials],
            'clear_credentials' => [],
        ]);
    }

    private function administrator(): User
    {
        return User::factory()->create([
            'role' => 'administrator',
            'is_active' => true,
            'admin_access' => true,
        ]);
    }
}
