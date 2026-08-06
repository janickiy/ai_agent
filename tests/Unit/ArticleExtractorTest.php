<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\NewsMonitor\Services\ArticleExtractor;
use App\Modules\NewsMonitor\Services\ContentNormalizer;
use App\Modules\NewsMonitor\Services\UrlCanonicalizer;
use PHPUnit\Framework\TestCase;

final class ArticleExtractorTest extends TestCase
{
    /**
     * Проверяет базовое извлечение неизменённых полей и даты из schema.org.
     */
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
        self::assertSame('Основной текст новости о строительстве.', $article->body);
        self::assertSame('https://example.org/images/bridge.jpg', $article->imageUrl);
        self::assertSame('2026-07-29T06:30:00+00:00', $article->publishedAt?->toIso8601String());
    }

    /**
     * Проверяет исправление страниц, у которых meta-description содержит только
     * название сайта: кратким описанием становится лид, а полным — все абзацы статьи.
     */
    public function test_it_rejects_site_name_as_description_and_extracts_full_article(): void
    {
        $extractor = new ArticleExtractor(new UrlCanonicalizer, new ContentNormalizer);
        $article = $extractor->extract(<<<'HTML'
            <html><head>
              <meta property="og:title" content="Юрий Трутнев: Дноуглубительный флот будет создан">
              <meta property="og:site_name" content="Правительство России">
              <meta name="description" content="Правительство России">
              <meta property="article:published_time" content="2026-08-05T08:00:00+03:00">
            </head><body>
              <nav>Поиск по сайту Новости Документы</nav>
              <div class="reader_article_body">
                <p>Юрий Трутнев провёл совещание по вопросу создания дноуглубительного флота для Северного морского пути.</p>
                <p>На совещании рассмотрели финансовую модель и сроки строительства специализированных судов.</p>
                <aside>Связанные новости</aside>
              </div>
              <div class="related-news">Другие материалы сайта</div>
            </body></html>
            HTML, 'https://government.ru/news/59519/', [
            'source_name' => 'Правительство России',
        ]);

        self::assertSame(
            'Юрий Трутнев провёл совещание по вопросу создания дноуглубительного флота для Северного морского пути.',
            $article->description,
        );
        self::assertSame(
            "Юрий Трутнев провёл совещание по вопросу создания дноуглубительного флота для Северного морского пути.\n"
            .'На совещании рассмотрели финансовую модель и сроки строительства специализированных судов.',
            $article->body,
        );
        self::assertStringNotContainsString('Поиск по сайту', $article->body);
        self::assertStringNotContainsString('Связанные новости', $article->body);
    }

    /**
     * Проверяет приоритет полного текста из JSON-LD, когда HTML-контейнер статьи отсутствует.
     */
    public function test_it_uses_json_ld_article_body_as_full_description(): void
    {
        $extractor = new ArticleExtractor(new UrlCanonicalizer, new ContentNormalizer);
        $article = $extractor->extract(<<<'HTML'
            <html><head>
              <script type="application/ld+json">
                {"@type":"NewsArticle","headline":"Новость","description":"Краткое описание публикации.","articleBody":"Первый абзац полного текста.\n\nВторой абзац полного текста.","datePublished":"2026-08-05T08:00:00+03:00"}
              </script>
            </head><body><h1>Новость</h1><div>Служебное содержимое сайта</div></body></html>
            HTML, 'https://example.org/news/json-ld');

        self::assertSame('Краткое описание публикации.', $article->description);
        self::assertSame("Первый абзац полного текста.\n\nВторой абзац полного текста.", $article->body);
    }
}
