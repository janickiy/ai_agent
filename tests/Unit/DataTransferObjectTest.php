<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Admin\AdministratorData;
use App\DTO\Catalog\SourceData;
use App\DTO\Pipeline\SourceItemData;
use App\DTO\Settings\AISettingsData;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DataTransferObjectTest extends TestCase
{
    public function test_administrator_data_omits_empty_password_and_forces_administrator_access(): void
    {
        $dto = AdministratorData::fromArray([
            'login' => ' MAIN.ADMIN ',
            'password' => '   ',
            'is_active' => true,
            'role' => 'viewer',
            'admin_access' => false,
        ]);

        self::assertSame([
            'login' => 'main.admin',
            'role' => 'administrator',
            'is_active' => true,
            'admin_access' => true,
        ], $dto->toArray());
        self::assertArrayNotHasKey('password', $dto->toArray());
    }

    public function test_source_data_keeps_unique_category_ids_outside_model_attributes(): void
    {
        $dto = SourceData::fromArray([
            'name' => 'Тестовый источник',
            'domain' => 'EXAMPLE.TEST',
            'type' => 'rss',
            'source_class' => 'industry_media',
            'trust_score' => 75,
            'base_url' => 'https://example.test',
            'feed_url' => '',
            'is_active' => true,
            'is_trusted' => false,
            'is_allowed' => true,
            'poll_interval_minutes' => 30,
            'request_limit' => 20,
            'timeout_seconds' => 10,
            'max_attempts' => 3,
            'category_ids' => ['2', 2, 1, '1'],
        ]);

        self::assertSame([2, 1], $dto->categoryIds());
        self::assertArrayNotHasKey('category_ids', $dto->toArray());
        self::assertSame('example.test', $dto->toArray()['domain']);
        self::assertNull($dto->toArray()['feed_url']);
    }

    public function test_ai_settings_data_never_exposes_credentials_or_clear_flags_in_public_array(): void
    {
        $dto = AISettingsData::fromArray([
            'provider' => 'gigachat',
            'provider_settings' => [
                'gigachat' => [
                    'api_url' => 'https://api.giga.chat/v1',
                    'model' => 'GigaChat',
                    'auth_key' => 'must-not-leak',
                    'client_id' => 'must-not-leak-client',
                    'client_secret' => 'must-not-leak-secret',
                ],
                'yandexgpt' => [
                    'model' => 'yandexgpt/latest',
                    'api_key' => 'must-not-leak-yandex-key',
                    'iam_token' => 'must-not-leak-yandex-token',
                ],
                'openai' => [
                    'model' => 'gpt-test',
                    'api_key' => 'must-not-leak-openai-key',
                ],
                'gemini' => [
                    'model' => 'gemini-test',
                    'api_key' => 'must-not-leak-gemini-key',
                ],
            ],
            'provider_credentials' => [
                'gigachat' => ['auth_key' => 'credential-value'],
                'openai' => ['api_key' => 'credential-value'],
                'gemini' => ['api_key' => 'credential-value'],
            ],
            'clear_credentials' => [
                'gigachat' => true,
                'openai' => false,
                'gemini' => false,
            ],
        ]);

        $public = $dto->toArray();

        self::assertSame([
            'provider' => 'gigachat',
            'gigachat' => [
                'api_url' => 'https://api.giga.chat/v1',
                'model' => 'GigaChat',
            ],
            'yandexgpt' => ['model' => 'yandexgpt/latest'],
            'openai' => ['model' => 'gpt-test'],
            'gemini' => ['model' => 'gemini-test'],
        ], $public);
        self::assertArrayNotHasKey('provider_credentials', $public);
        self::assertArrayNotHasKey('clear_credentials', $public);
        self::assertStringNotContainsString('must-not-leak', json_encode($public, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('credential-value', json_encode($public, JSON_THROW_ON_ERROR));
    }

    public function test_ai_settings_data_rejects_incomplete_provider_settings(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AISettingsData::fromArray([
            'provider' => 'gigachat',
            'provider_settings' => ['gigachat' => []],
        ]);
    }

    public function test_source_item_data_rejects_fields_outside_its_write_contract(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SourceItemData::fromArray([
            'status' => 'analyzed',
            'unexpected_field' => 'must-not-be-persisted',
        ]);
    }
}
