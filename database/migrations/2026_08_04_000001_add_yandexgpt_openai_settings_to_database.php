<?php

declare(strict_types=1);

use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<string, mixed>> */
    private const PROVIDER_DEFAULTS = [
        'yandexgpt' => [
            'api_url' => 'https://ai.api.cloud.yandex.net/v1',
            'folder_id' => '',
            'model' => 'yandexgpt/latest',
            'embedding_model' => 'text-search-doc/latest',
            'timeout' => 120,
            'connect_timeout' => 10,
            'max_attempts' => 5,
            'verify_ssl' => true,
        ],
        'openai' => [
            'api_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-5.6',
            'embedding_model' => 'text-embedding-3-small',
            'organization' => '',
            'project' => '',
            'timeout' => 120,
            'connect_timeout' => 10,
            'max_attempts' => 5,
            'verify_ssl' => true,
        ],
    ];

    /** @var array<string, list<string>> */
    private const CREDENTIAL_FIELDS = [
        'ai.yandexgpt.credentials' => ['api_key', 'iam_token'],
        'ai.openai.credentials' => ['api_key'],
    ];

    public function up(): void
    {
        $table = NewsTables::name('settings');
        if (! Schema::hasTable($table)) {
            return;
        }

        DB::transaction(function () use ($table): void {
            $this->addPublicDefaults($table);
            $this->addCredentialRows($table);
        });
    }

    public function down(): void
    {
        // Настройки намеренно сохраняются: после миграции они могут быть изменены пользователем.
    }

    private function addPublicDefaults(string $table): void
    {
        $row = DB::table($table)->where('key', 'ai')->first();
        $now = now()->utc();

        if ($row === null) {
            DB::table($table)->insert([
                'key' => 'ai',
                'value' => $this->encode([
                    'provider' => 'rules',
                    ...self::PROVIDER_DEFAULTS,
                ]),
                'is_secret' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        $values = $this->decode($row->value ?? null);
        if ($values === null) {
            return;
        }

        $updated = $values;
        foreach (self::PROVIDER_DEFAULTS as $provider => $defaults) {
            if (! array_key_exists($provider, $updated)) {
                $updated[$provider] = $defaults;

                continue;
            }

            if (is_array($updated[$provider])) {
                $updated[$provider] = array_replace($defaults, $updated[$provider]);
            }
        }

        if ($updated === $values) {
            return;
        }

        DB::table($table)
            ->where('key', 'ai')
            ->update([
                'value' => $this->encode($updated),
                'updated_at' => $now,
            ]);
    }

    private function addCredentialRows(string $table): void
    {
        $now = now()->utc();

        foreach (self::CREDENTIAL_FIELDS as $key => $fields) {
            if (DB::table($table)->where('key', $key)->exists()) {
                continue;
            }

            $credentials = [];
            foreach ($fields as $field) {
                $credentials[$field] = $this->encrypt('');
            }

            DB::table($table)->insert([
                'key' => $key,
                'value' => $this->encode($credentials),
                'is_secret' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** @return array<string, mixed>|null */
    private function decode(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string, mixed> $value */
    private function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function encrypt(string $value): ?string
    {
        return $value === '' ? null : Crypt::encryptString($value);
    }
};
