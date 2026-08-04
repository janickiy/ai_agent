<?php

declare(strict_types=1);

use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable(NewsTables::name('settings'))) {
            return;
        }

        $provider = strtolower(trim((string) env('AI_PROVIDER')));
        $authKey = trim((string) env('GIGACHAT_AUTH_KEY'));
        $clientId = trim((string) env('GIGACHAT_CLIENT_ID'));
        $clientSecret = trim((string) env('GIGACHAT_CLIENT_SECRET'));
        $hasCredentials = $authKey !== '' || ($clientId !== '' && $clientSecret !== '');

        $table = NewsTables::name('settings');
        $now = now()->utc();

        if (! DB::table($table)->where('key', 'ai')->exists()) {
            DB::table($table)->insert([
                'key' => 'ai',
                'value' => json_encode([
                    'provider' => $provider === 'gigachat' && $hasCredentials ? 'gigachat' : 'rules',
                    'gigachat' => [
                        'auth_url' => (string) env('GIGACHAT_AUTH_URL', 'https://ngw.devices.sberbank.ru:9443/api/v2/oauth'),
                        'api_url' => (string) env('GIGACHAT_API_URL', 'https://api.giga.chat/v1'),
                        'scope' => (string) env('GIGACHAT_SCOPE', 'GIGACHAT_API_PERS'),
                        'model' => (string) env('GIGACHAT_MODEL', 'GigaChat-2-Max'),
                        'embedding_model' => (string) env('GIGACHAT_EMBEDDING_MODEL', 'EmbeddingsGigaR'),
                        'embedding_fallback' => filter_var(env('GIGACHAT_EMBEDDING_FALLBACK', true), FILTER_VALIDATE_BOOL),
                        'timeout' => (int) env('GIGACHAT_TIMEOUT', 120),
                        'connect_timeout' => (int) env('GIGACHAT_CONNECT_TIMEOUT', 10),
                        'max_attempts' => (int) env('GIGACHAT_MAX_ATTEMPTS', 5),
                        'verify_ssl' => true,
                    ],
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'is_secret' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table($table)->where('key', 'ai.gigachat.credentials')->exists()) {
            DB::table($table)->insert([
                'key' => 'ai.gigachat.credentials',
                'value' => json_encode([
                    'auth_key' => $this->encrypt($authKey),
                    'client_id' => $this->encrypt($clientId),
                    'client_secret' => $this->encrypt($clientSecret),
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'is_secret' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Перенесённые секреты намеренно не удаляются при откате миграции.
    }

    private function encrypt(string $value): ?string
    {
        return $value === '' ? null : Crypt::encryptString($value);
    }
};
