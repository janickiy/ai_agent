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
}
