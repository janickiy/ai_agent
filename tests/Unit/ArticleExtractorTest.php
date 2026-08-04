<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\NewsMonitor\Services\ArticleExtractor;
use App\Modules\NewsMonitor\Services\ContentNormalizer;
use App\Modules\NewsMonitor\Services\UrlCanonicalizer;
use PHPUnit\Framework\TestCase;

final class ArticleExtractorTest extends TestCase
{
    public function test_it_extracts_original_fields_and_schema_date(): void
    {
        $extractor = new ArticleExtractor(new UrlCanonicalizer, new ContentNormalizer);
        $article = $extractor->extract(<<<'HTML'
            <html><head>
              <link rel="canonical" href="/news/bridge?utm_source=test">
              <meta property="og:title" content="Построен новый мост">
              <meta name="description" content="Точная копия краткого описания.">
              <meta property="og:image" content="/images/bridge.jpg">
              <script type="application/ld+json">{"@type":"NewsArticle","datePublished":"2026-07-29T09:30:00+03:00"}</script>
            </head><body><article><p>Основной текст новости о строительстве.</p></article></body></html>
            HTML, 'https://example.org/discovery');

        self::assertSame('https://example.org/news/bridge', $article->canonicalUrl);
        self::assertSame('Построен новый мост', $article->title);
        self::assertSame('Точная копия краткого описания.', $article->description);
        self::assertSame('https://example.org/images/bridge.jpg', $article->imageUrl);
        self::assertSame('2026-07-29T06:30:00+00:00', $article->publishedAt?->toIso8601String());
    }
}
