<?php

use App\Modules\NewsMonitor\Services\HashtagNormalizer;
use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Исправляет уже сохранённые хэштеги готовых публикаций по тем же правилам,
     * которые применяются к новым ответам AI-провайдеров.
     */
    public function up(): void
    {
        $posts = NewsTables::name('posts');
        $normalizer = new HashtagNormalizer;

        DB::table($posts)
            ->select(['id', 'hashtags'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($normalizer, $posts): void {
                foreach ($rows as $row) {
                    $hashtags = json_decode((string) $row->hashtags, true);
                    $hashtags = is_array($hashtags) ? array_values(array_filter(
                        $hashtags,
                        static fn (mixed $value): bool => is_string($value),
                    )) : [];
                    $normalized = $normalizer->normalize($hashtags);

                    if ($normalized !== []) {
                        DB::table($posts)
                            ->where('id', $row->id)
                            ->update(['hashtags' => json_encode($normalized, JSON_UNESCAPED_UNICODE)]);
                    }
                }
            });
    }

    /**
     * Не изменяет данные при откате, потому что удалённые служебные символы
     * невозможно достоверно восстановить.
     */
    public function down(): void
    {
        // Нормализация данных необратима и не меняет структуру таблицы.
    }
};
