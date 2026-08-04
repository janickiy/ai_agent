<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Services;

use App\Modules\NewsMonitor\DTO\ExtractedArticle;
use App\Modules\NewsMonitor\Exceptions\ExtractionException;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Throwable;

final class ArticleExtractor
{
    public function __construct(
        private readonly UrlCanonicalizer $canonicalizer,
        private readonly ContentNormalizer $normalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $fallback
     */
    public function extract(string $html, string $fetchedUrl, array $fallback = []): ExtractedArticle
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8">'.$html,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            throw new ExtractionException('HTML document cannot be parsed.');
        }

        $xpath = new DOMXPath($document);
        $canonical = $this->attribute($xpath, "//link[translate(@rel,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='canonical']", 'href')
            ?: $fetchedUrl;
        $canonical = $this->resolveUrl($fetchedUrl, $canonical);

        $title = $this->meta($xpath, 'property', 'og:title')
            ?: $this->text($xpath, '//title')
            ?: (string) ($fallback['title'] ?? '');
        $description = $this->meta($xpath, 'name', 'description')
            ?: $this->meta($xpath, 'property', 'og:description')
            ?: $this->text($xpath, '(//article//p[normalize-space()])[1]')
            ?: $this->text($xpath, '(//main//p[normalize-space()])[1]')
            ?: (string) ($fallback['description'] ?? '');
        $body = $this->text($xpath, '//article')
            ?: $this->text($xpath, '//main')
            ?: $this->text($xpath, '//body');
        $image = $this->meta($xpath, 'property', 'og:image')
            ?: $this->meta($xpath, 'name', 'twitter:image')
            ?: $this->attribute($xpath, '(//article//img[@src])[1]', 'src');
        $publishedAt = $this->publishedAt($xpath)
            ?? $this->parseDate($fallback['published_at'] ?? null);

        $title = $this->normalizer->copiedField($title);
        $description = $this->normalizer->copiedField($description);
        $body = $this->normalizer->body($body);

        return new ExtractedArticle(
            canonicalUrl: $this->canonicalizer->canonicalize($canonical),
            title: $title,
            description: $description,
            body: $body,
            imageUrl: $image === '' ? null : $this->resolveUrl($fetchedUrl, $image),
            publishedAt: $publishedAt,
            meta: [
                'extractor_version' => 'dom-v1',
                'title_hash' => hash('sha256', $title),
                'description_hash' => hash('sha256', $description),
            ],
        );
    }

    private function publishedAt(DOMXPath $xpath): ?CarbonImmutable
    {
        foreach ($xpath->query("//script[@type='application/ld+json']") ?: [] as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $json = json_decode(trim($node->textContent), true);
            $value = $this->findDatePublished($json);
            if ($date = $this->parseDate($value)) {
                return $date;
            }
        }

        foreach ([
            $this->meta($xpath, 'property', 'article:published_time'),
            $this->meta($xpath, 'name', 'date'),
            $this->attribute($xpath, "//*[@itemprop='datePublished']", 'content'),
            $this->attribute($xpath, '//time[@datetime]', 'datetime'),
        ] as $value) {
            if ($date = $this->parseDate($value)) {
                return $date;
            }
        }

        return null;
    }

    private function findDatePublished(mixed $data): ?string
    {
        if (! is_array($data)) {
            return null;
        }
        if (isset($data['datePublished']) && is_string($data['datePublished'])) {
            return $data['datePublished'];
        }
        foreach ($data as $value) {
            if (($found = $this->findDatePublished($value)) !== null) {
                return $found;
            }
        }

        return null;
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    private function meta(DOMXPath $xpath, string $attribute, string $value): string
    {
        return $this->attribute(
            $xpath,
            sprintf(
                "//meta[translate(@%s,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='%s']",
                $attribute,
                mb_strtolower($value),
            ),
            'content',
        );
    }

    private function attribute(DOMXPath $xpath, string $query, string $attribute): string
    {
        $node = $xpath->query($query)?->item(0);

        return $node instanceof DOMElement ? trim($node->getAttribute($attribute)) : '';
    }

    private function text(DOMXPath $xpath, string $query): string
    {
        return trim($xpath->query($query)?->item(0)?->textContent ?? '');
    }

    private function resolveUrl(string $base, string $candidate): string
    {
        try {
            return (string) UriResolver::resolve(new Uri($base), new Uri(trim($candidate)));
        } catch (Throwable) {
            return $candidate;
        }
    }
}
