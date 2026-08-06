<?php

use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const END_MARKER = 'Материалы по теме';

    /**
     * Очищает полные описания старых публикаций РБК от шапки, ленты новостей,
     * футера и JavaScript, которые сохранял предыдущий экстрактор страницы.
     */
    public function up(): void
    {
        $posts = NewsTables::name('posts');

        DB::table($posts)
            ->select(['id', 'title_original', 'description_original', 'full_description_original'])
            ->where('source_name', 'РБК Недвижимость')
            ->whereNotNull('full_description_original')
            ->where('full_description_original', 'like', '%--auto-if-no-script%')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($posts): void {
                foreach ($rows as $row) {
                    $full = $this->repair(
                        (string) $row->title_original,
                        (string) $row->description_original,
                        (string) $row->full_description_original,
                    );
                    if ($full === null) {
                        continue;
                    }

                    DB::table($posts)
                        ->where('id', $row->id)
                        ->update(['full_description_original' => $full]);
                }
            });
    }

    /**
     * Не восстанавливает удалённые служебные блоки при откате миграции.
     */
    public function down(): void
    {
        // Исправление исторических данных необратимо и не меняет структуру таблицы.
    }

    /**
     * Находит основное вхождение заголовка рядом с кратким описанием и обрезает
     * содержимое перед повторным блоком тематических рекомендаций.
     */
    private function repair(string $title, string $description, string $legacyBody): ?string
    {
        if ($title === '' || $description === '') {
            return null;
        }

        $position = $this->articleTitlePosition($legacyBody, $title, $description);
        if ($position === null) {
            return null;
        }

        $article = ltrim(mb_substr($legacyBody, $position + mb_strlen($title)));
        $end = mb_strlen($article);
        foreach ([
            self::END_MARKER,
            ' rbc.group rbc.group Прямой эфир',
            ' Прямой эфир Ошибка воспроизведения',
            ' Лента новостей RA.config.set',
            ' rbc.group РБК О компании',
        ] as $marker) {
            $markerPosition = mb_strpos($article, $marker);
            if ($markerPosition !== false) {
                $end = min($end, $markerPosition);
            }
        }
        if ($end === mb_strlen($article)) {
            return null;
        }

        $article = trim(mb_substr($article, 0, $end));
        $article = preg_replace('/(?<=[\p{Ll}\d»”][.!?])(?=[\p{Lu}«])/u', "\n\n", $article) ?? $article;

        return mb_strlen($article) >= mb_strlen($description) ? $article : null;
    }

    /**
     * Выбирает то вхождение заголовка, после которого краткое описание находится
     * ближе всего; соседние блоки рекомендаций при этом игнорируются.
     */
    private function articleTitlePosition(string $body, string $title, string $description): ?int
    {
        $description = rtrim($description, ".… \t\n\r\0\x0B");
        $description = mb_substr($description, 0, min(120, mb_strlen($description)));
        $offset = 0;
        $bestPosition = null;
        $bestDistance = PHP_INT_MAX;

        while (($position = mb_strpos($body, $title, $offset)) !== false) {
            $afterTitle = mb_substr($body, $position + mb_strlen($title), 1_500);
            $descriptionPosition = mb_strpos($afterTitle, $description);
            if ($descriptionPosition !== false && $descriptionPosition < $bestDistance) {
                $bestPosition = $position;
                $bestDistance = $descriptionPosition;
            }

            $offset = $position + mb_strlen($title);
        }

        return $bestPosition;
    }
};
