<?php

use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONTENT_MARKER = 'Предыдущая новость Следующая новость ';

    private const WORK_MARKER = 'Работа Правительства:';

    /**
     * Исправляет старые публикации government.ru, в которых прежний экстрактор
     * принял название сайта за краткое описание и сохранил всю страницу целиком.
     */
    public function up(): void
    {
        $posts = NewsTables::name('posts');

        DB::table($posts)
            ->select(['id', 'title_original', 'source_name', 'description_original', 'full_description_original'])
            ->where('source_url', 'like', '%government.ru/%')
            ->whereNotNull('full_description_original')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($posts): void {
                foreach ($rows as $row) {
                    $repaired = $this->repair(
                        (string) $row->title_original,
                        (string) $row->full_description_original,
                    );
                    if ($repaired === null) {
                        continue;
                    }

                    DB::table($posts)
                        ->where('id', $row->id)
                        ->update([
                            'description_original' => $row->description_original === $row->source_name
                                ? $repaired['short']
                                : $row->description_original,
                            'full_description_original' => $repaired['full'],
                        ]);
                }
            });
    }

    /**
     * Не восстанавливает ошибочный служебный текст при откате миграции.
     */
    public function down(): void
    {
        // Исправление исторических данных необратимо и не меняет структуру таблицы.
    }

    /**
     * Выделяет повторяющийся лид и основной текст из сохранённого содержимого
     * старой версии страницы Правительства России.
     *
     * @return array{short: string, full: string}|null
     */
    private function repair(string $title, string $legacyBody): ?array
    {
        $markerPosition = mb_strpos($legacyBody, self::CONTENT_MARKER);
        if ($markerPosition !== false) {
            $before = mb_substr($legacyBody, 0, $markerPosition);
            $article = trim(mb_substr($legacyBody, $markerPosition + mb_strlen(self::CONTENT_MARKER)));
            $article = $this->beforeTrailingMetadata($article);
            $short = $this->duplicatedPrefix($before, $article);

            return $short === null ? null : $this->result($short, $article);
        }

        $titlePosition = mb_strpos($legacyBody, $title);
        if ($title === '' || $titlePosition === false) {
            return null;
        }

        $afterTitle = mb_substr($legacyBody, $titlePosition + mb_strlen($title));
        $workPosition = mb_strpos($afterTitle, self::WORK_MARKER);
        if ($workPosition === false) {
            return null;
        }

        $article = trim(mb_substr($afterTitle, $workPosition + mb_strlen(self::WORK_MARKER)));
        $topics = $this->trailingTopics($legacyBody);
        if ($topics !== '' && str_starts_with($article, $topics)) {
            $article = ltrim(mb_substr($article, mb_strlen($topics)));
        }
        if (str_starts_with($article, $title)) {
            $article = ltrim(mb_substr($article, mb_strlen($title)), " \t\n\r\0\x0B—–-:|");
        }

        $article = $this->beforeTrailingMetadata($article);
        $short = $this->firstLegacyParagraph($article);

        return $short === null ? null : $this->result($short, $article);
    }

    /**
     * Формирует очищенные краткое и полное описания и восстанавливает границы
     * абзацев, которые были утрачены старым DOM-экстрактором.
     *
     * @return array{short: string, full: string}
     */
    private function result(string $short, string $article): array
    {
        $remainder = ltrim(mb_substr($article, mb_strlen($short)), " \t\n\r\0\x0B—–-:|");
        $remainder = preg_replace('/(?<=[\p{Ll}\d»”][.!?])(?=[\p{Lu}«])/u', "\n\n", $remainder) ?? $remainder;

        return [
            'short' => $short,
            'full' => $remainder === '' ? $short : $short."\n\n".$remainder,
        ];
    }

    /**
     * Обрезает повторные рубрики и служебные метаданные, расположенные после статьи.
     */
    private function beforeTrailingMetadata(string $article): string
    {
        $position = mb_strrpos($article, ' '.self::WORK_MARKER);

        return $position === false ? trim($article) : trim(mb_substr($article, 0, $position));
    }

    /**
     * Извлекает строку тематических рубрик из завершающих метаданных старой страницы.
     */
    private function trailingTopics(string $legacyBody): string
    {
        $position = mb_strrpos($legacyBody, ' '.self::WORK_MARKER);
        if ($position === false) {
            return '';
        }

        $tail = mb_substr($legacyBody, $position + mb_strlen(' '.self::WORK_MARKER));
        $end = mb_strlen($tail);
        foreach ([
            ' Поручения:',
            ' Министерства и ведомства',
            ' Именной указатель:',
            ' Регионы:',
            ' Правительство Российской Федерации',
        ] as $marker) {
            $markerPosition = mb_strpos($tail, $marker);
            if ($markerPosition !== false) {
                $end = min($end, $markerPosition);
            }
        }

        return trim(mb_substr($tail, 0, $end));
    }

    /**
     * Возвращает первый абзац слитного текста по месту, где между предложениями
     * отсутствует пробел — так старая версия объединяла соседние HTML-параграфы.
     */
    private function firstLegacyParagraph(string $article): ?string
    {
        if (preg_match('/^(.{40,3000}?[\p{Ll}\d»”][.!?])(?=[\p{Lu}«])/us', $article, $matches) === 1) {
            return trim($matches[1]);
        }
        if (preg_match('/^(.{40,600}?[.!?])(?:\s+|$)/us', $article, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Находит максимальный начальный фрагмент статьи, который уже встречался
     * перед служебным переключателем новостей и поэтому является лидом.
     */
    private function duplicatedPrefix(string $before, string $article): ?string
    {
        $words = preg_split('/\s+/u', trim($article)) ?: [];
        $best = null;

        for ($length = 5; $length <= min(80, count($words)); $length++) {
            $candidate = implode(' ', array_slice($words, 0, $length));
            if (! str_contains($before, $candidate)) {
                if ($best !== null) {
                    break;
                }

                continue;
            }

            $best = $candidate;
        }

        return $best !== null && mb_strlen($best) >= 40 ? $best : null;
    }
};
