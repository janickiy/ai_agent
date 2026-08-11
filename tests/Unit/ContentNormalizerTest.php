<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\NewsMonitor\Services\ContentNormalizer;
use PHPUnit\Framework\TestCase;

final class ContentNormalizerTest extends TestCase
{
    /**
     * Проверяет техническую очистку короткого скопированного поля.
     */
    public function test_it_only_applies_allowed_technical_normalization(): void
    {
        $normalizer = new ContentNormalizer;

        self::assertSame(
            'Заголовок — точная копия & без HTML',
            $normalizer->copiedField("  <b>Заголовок</b>\n— точная   копия &amp; без HTML  "),
        );
    }

    /**
     * Проверяет сохранение абзацев в полном описании статьи.
     */
    public function test_body_preserves_paragraph_breaks(): void
    {
        $normalizer = new ContentNormalizer;

        self::assertSame(
            "Первый абзац.\n\nВторой абзац.",
            $normalizer->body("  Первый   абзац.\r\n\r\n\r\n Второй абзац.  "),
        );
    }

    /**
     * Проверяет удаление точных повторов абзацев без изменения уникального текста.
     */
    public function test_body_removes_repeated_content_blocks(): void
    {
        $normalizer = new ContentNormalizer;

        self::assertSame(
            "Первый содержательный абзац новости.\n\nВторой уникальный абзац новости.",
            $normalizer->body(
                "Первый содержательный абзац новости.\n\n"
                ."Второй уникальный абзац новости.\n\n"
                .'  Первый содержательный абзац новости.  ',
            ),
        );
    }

    /**
     * Проверяет исключение лида из полного текста, когда тот уже передаётся
     * отдельным кратким описанием, и защиту от получения пустого результата.
     */
    public function test_publication_body_removes_only_a_leading_short_description(): void
    {
        $normalizer = new ContentNormalizer;
        $short = 'Краткое описание важной строительной новости.';

        self::assertSame(
            'Основной текст с подробностями проекта.',
            $normalizer->publicationBody(
                $short."\n\nОсновной текст с подробностями проекта.\n\nОсновной текст с подробностями проекта.",
                $short,
            ),
        );
        self::assertSame($short, $normalizer->publicationBody($short, $short));
        self::assertSame(
            'Вводный текст отличается. Краткое описание важной строительной новости.',
            $normalizer->publicationBody(
                'Вводный текст отличается. Краткое описание важной строительной новости.',
                $short,
            ),
        );
    }
}
