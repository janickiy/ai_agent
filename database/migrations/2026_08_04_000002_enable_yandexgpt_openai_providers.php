<?php

declare(strict_types=1);

use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = NewsTables::name('settings');
        if (! Schema::hasTable($table)) {
            return;
        }

        DB::transaction(function () use ($table): void {
            $row = DB::table($table)
                ->where('key', 'ai')
                ->lockForUpdate()
                ->first();
            $values = $this->decode($row?->value);
            if ($values === null) {
                return;
            }

            $updated = $values;
            if (is_array($updated['yandexgpt'] ?? null)) {
                $embeddingModel = trim((string) ($updated['yandexgpt']['embedding_model'] ?? ''));
                if ($embeddingModel === '') {
                    $updated['yandexgpt']['embedding_model'] = 'text-search-doc/latest';
                }
            }

            if (
                is_array($updated['openai'] ?? null)
                && ($updated['openai']['model'] ?? null) === 'gpt-5.6-sol'
            ) {
                $updated['openai']['model'] = 'gpt-5.6';
            }

            if ($updated === $values) {
                return;
            }

            DB::table($table)
                ->where('key', 'ai')
                ->update([
                    'value' => json_encode($updated, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now()->utc(),
                ]);
        });
    }

    public function down(): void
    {
        // Пользовательские настройки провайдеров намеренно сохраняются при откате.
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
};
