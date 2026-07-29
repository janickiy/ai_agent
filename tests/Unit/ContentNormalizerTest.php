<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\NewsMonitor\Services\ContentNormalizer;
use PHPUnit\Framework\TestCase;

final class ContentNormalizerTest extends TestCase
{
    public function test_it_only_applies_allowed_technical_normalization(): void
    {
        $normalizer = new ContentNormalizer;

        self::assertSame(
            'Заголовок — точная копия & без HTML',
            $normalizer->copiedField("  <b>Заголовок</b>\n— точная   копия &amp; без HTML  "),
        );
    }
}
