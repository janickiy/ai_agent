<?php

declare(strict_types=1);

use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, mixed> */
    private const DEFAULTS = [
        'api_url' => 'https://generativelanguage.googleapis.com/v1beta',
        'model' => 'gemini-3.6-flash',
        'embedding_model' => 'gemini-embedding-2',
        'timeout' => 120,
        'connect_timeout' => 10,
        'max_attempts' => 5,
        'verify_ssl' => true,
    ];

    /**
     * Добавляет публичные настройки и отдельное секретное хранилище API-ключа Gemini.
     */
    public function up(): void
    {
        $table = NewsTables::name('settings');
        if (! Schema::hasTable($table)) {
            return;
        }

        DB::transaction(function () use ($table): void {
            $this->addPublicDefaults($table);
            $this->addCredentialRow($table);
        });
    }

    /**
     * Сохраняет пользовательские настройки при откате, чтобы не уничтожать API-ключ и параметры моделей.
     */
    public function down(): void
    {
        // Пользовательские настройки Gemini намеренно не удаляются при откате.
    }

    /**
     * Дополняет существующую публичную AI-конфигурацию значениями Gemini без перезаписи других провайдеров.
     */
    private function addPublicDefaults(string $table): void
    {
        $row = DB::table($table)
            ->where('key', 'ai')
            ->lockForUpdate()
            ->first();
        $now = now()->utc();

        if ($row === null) {
            DB::table($table)->insert([
                'key' => 'ai',
                'value' => $this->encode([
                    'provider' => 'rules',
                    'gemini' => self::DEFAULTS,
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

        $gemini = is_array($values['gemini'] ?? null) ? $values['gemini'] : [];
        $updated = [
            ...$values,
            'gemini' => array_replace(self::DEFAULTS, $gemini),
        ];

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

    /**
     * Создаёт помеченную как секретную запись для зашифрованного API-ключа Gemini.
     */
    private function addCredentialRow(string $table): void
    {
        if (DB::table($table)->where('key', 'ai.gemini.credentials')->exists()) {
            return;
        }

        $now = now()->utc();
        DB::table($table)->insert([
            'key' => 'ai.gemini.credentials',
            'value' => $this->encode(['api_key' => null]),
            'is_secret' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Декодирует JSON-представление системной настройки в ассоциативный массив.
     *
     * @return array<string, mixed>|null
     */
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

    /**
     * Кодирует настройку в JSON без экранирования URL.
     *
     * @param  array<string, mixed>  $value
     */
    private function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
};
