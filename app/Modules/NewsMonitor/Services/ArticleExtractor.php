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

/**
 * Извлекает структурированные данные новости из загруженного HTML-документа.
 *
 * Сервис выбирает заголовок, описание, текст, изображение, канонический URL и дату
 * из метатегов, JSON-LD и семантических элементов, после чего нормализует результат.
 */
final class ArticleExtractor
{
    private const UPPERCASE = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private const LOWERCASE = 'abcdefghijklmnopqrstuvwxyz';

    /**
     * Получает сервисы канонизации URL и технической очистки извлечённого текста.
     */
    public function __construct(
        private readonly UrlCanonicalizer $canonicalizer,
        private readonly ContentNormalizer $normalizer,
    ) {}

    /**
     * Разбирает HTML статьи и возвращает единый DTO извлечённого материала.
     *
     * Недостающие заголовок, описание и дата могут быть взяты из данных RSS/Atom,
     * а относительные ссылки преобразуются в абсолютные относительно фактического URL страницы.
     *
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
            ?: $this->text($xpath, '(//h1[normalize-space()])[1]')
            ?: $this->text($xpath, '//title')
            ?: (string) ($fallback['title'] ?? '');
        $title = $this->normalizer->copiedField($title);
        $contentNode = $this->contentNode($xpath);
        $body = $this->articleBody($xpath, $contentNode, $title);
        $description = $this->description($xpath, $contentNode, $title, $body, $fallback);
        $image = $this->meta($xpath, 'property', 'og:image')
            ?: $this->meta($xpath, 'name', 'twitter:image')
            ?: $this->attribute($xpath, '(//article//img[@src])[1]', 'src');
        $publishedAt = $this->publishedAt($xpath)
            ?? $this->parseDate($fallback['published_at'] ?? null);

        return new ExtractedArticle(
            canonicalUrl: $this->canonicalizer->canonicalize($canonical),
            title: $title,
            description: $description,
            body: $body,
            imageUrl: $image === '' ? null : $this->resolveUrl($fetchedUrl, $image),
            publishedAt: $publishedAt,
            meta: [
                'extractor_version' => 'dom-v2',
                'title_hash' => hash('sha256', $title),
                'description_hash' => hash('sha256', $description),
            ],
        );
    }

    /**
     * Выбирает краткое описание статьи, отбрасывая название сайта, повтор заголовка
     * и навигационный текст, ошибочно опубликованные в HTML meta-description.
     *
     * @param  array<string, mixed>  $fallback
     */
    private function description(
        DOMXPath $xpath,
        ?DOMNode $contentNode,
        string $title,
        string $body,
        array $fallback,
    ): string {
        $siteNames = array_filter([
            $this->meta($xpath, 'property', 'og:site_name'),
            $this->meta($xpath, 'name', 'application-name'),
            (string) ($fallback['source_name'] ?? ''),
        ]);
        $candidates = [
            $this->jsonLdValue($xpath, 'description'),
            $this->meta($xpath, 'property', 'og:description'),
            $this->meta($xpath, 'name', 'description'),
            $this->lead($xpath),
            (string) ($fallback['description'] ?? ''),
            $this->firstParagraph($xpath, $contentNode),
            $this->firstBodyParagraph($body),
        ];

        foreach ($candidates as $candidate) {
            $description = $this->cleanDescriptionCandidate((string) $candidate, $title, $siteNames);
            if ($description !== null) {
                return $description;
            }
        }

        return '';
    }

    /**
     * Извлекает полный редакционный текст из JSON-LD либо наиболее вероятного
     * контейнера статьи и сохраняет границы абзацев.
     */
    private function articleBody(DOMXPath $xpath, ?DOMNode $contentNode, string $title): string
    {
        $body = $this->jsonLdValue($xpath, 'articleBody');
        if ($body === '' && $contentNode !== null) {
            $body = $this->nodeText($contentNode);
        }
        if ($body === '') {
            $body = $this->nodeText($xpath->query('//body')?->item(0));
        }

        $body = $this->normalizer->body($body);
        if ($title !== '' && str_starts_with($body, $title)) {
            $body = ltrim(mb_substr($body, mb_strlen($title)), " \t\n\r\0\x0B—–-:|");
        }

        return $body;
    }

    /**
     * Находит DOM-контейнер, который с наибольшей вероятностью содержит именно
     * редакционный текст статьи, а не меню, рекомендации и футер страницы.
     */
    private function contentNode(DOMXPath $xpath): ?DOMNode
    {
        $descriptor = sprintf(
            "translate(concat(' ', normalize-space(@class), ' ', normalize-space(@id), ' '),'%s','%s')",
            self::UPPERCASE,
            self::LOWERCASE,
        );
        $query = sprintf(
            "//*[@itemprop='articleBody'"
            ." or (contains(%1\$s,'article') and (contains(%1\$s,'body') or contains(%1\$s,'content') or contains(%1\$s,'text')))"
            ." or (contains(%1\$s,'news') and (contains(%1\$s,'body') or contains(%1\$s,'content') or contains(%1\$s,'text')))"
            ." or contains(%1\$s,'entry-content') or contains(%1\$s,'post-content')"
            ." or contains(%1\$s,'material-content') or contains(%1\$s,'reader_article')]",
            $descriptor,
        );
        $explicit = $this->bestContentNode($xpath->query($query) ?: []);
        if ($explicit !== null) {
            return $explicit;
        }

        return $this->bestContentNode($xpath->query(
            '//article | //main | //section[count(.//p[normalize-space()]) >= 2] | //div[count(.//p[normalize-space()]) >= 2]',
        ) ?: []);
    }

    /**
     * Выбирает самый содержательный контейнер из набора DOM-узлов по длине текста,
     * числу абзацев, ссылочной плотности и семантике CSS-классов.
     *
     * @param  iterable<DOMNode>  $nodes
     */
    private function bestContentNode(iterable $nodes): ?DOMNode
    {
        $best = null;
        $bestScore = -INF;

        foreach ($nodes as $node) {
            $score = $this->contentScore($node);
            if ($score > $bestScore) {
                $best = $node;
                $bestScore = $score;
            }
        }

        return $bestScore > 0 ? $best : null;
    }

    /**
     * Рассчитывает вес DOM-контейнера: длинные абзацы и профильные имена повышают
     * оценку, а меню, ссылки, реклама и служебные блоки понижают её.
     */
    private function contentScore(DOMNode $node): float
    {
        $text = $this->normalizer->copiedField($node->textContent);
        $length = mb_strlen($text);
        if ($length < 80) {
            return -INF;
        }

        $document = $node->ownerDocument;
        if (! $document instanceof DOMDocument) {
            return -INF;
        }

        $xpath = new DOMXPath($document);
        $paragraphLength = 0;
        $paragraphCount = 0;
        foreach ($xpath->query('.//p[normalize-space()]', $node) ?: [] as $paragraph) {
            $currentLength = mb_strlen($this->normalizer->copiedField($paragraph->textContent));
            if ($currentLength >= 40) {
                $paragraphLength += $currentLength;
                $paragraphCount++;
            }
        }

        $linkLength = 0;
        foreach ($xpath->query('.//a[normalize-space()]', $node) ?: [] as $link) {
            $linkLength += mb_strlen($this->normalizer->copiedField($link->textContent));
        }

        $descriptor = $node instanceof DOMElement
            ? mb_strtolower($node->getAttribute('class').' '.$node->getAttribute('id'))
            : '';
        if (preg_match('/nav|menu|footer|header|sidebar|related|recommend|comment|share|social|subscribe|pagination|advert|banner|search/u', $descriptor)) {
            return -INF;
        }

        $semanticBonus = 0;
        if ($node instanceof DOMElement && $node->getAttribute('itemprop') === 'articleBody') {
            $semanticBonus += 10_000;
        }
        if (strtolower($node->nodeName) === 'article') {
            $semanticBonus += 6_000;
        }
        if (preg_match('/article|news|story|material/u', $descriptor)) {
            $semanticBonus += 2_000;
        }
        if (preg_match('/body|content|text/u', $descriptor)) {
            $semanticBonus += 2_000;
        }

        $linkDensity = $length === 0 ? 0.0 : min(1.0, $linkLength / $length);

        return $semanticBonus
            + $paragraphLength
            + ($paragraphCount * 200)
            + (min($length, 20_000) * 0.15)
            - ($linkDensity * min($length, 20_000) * 2);
    }

    /**
     * Возвращает текст выбранного контейнера без скриптов, форм, навигации,
     * рекламы и связанных материалов, разделяя блочные элементы переносами строк.
     */
    private function nodeText(?DOMNode $node): string
    {
        if ($node === null) {
            return '';
        }

        $document = new DOMDocument;
        $root = $document->appendChild($document->importNode($node, true));
        $xpath = new DOMXPath($document);
        $unwanted = $xpath->query(
            './/script | .//style | .//noscript | .//svg | .//canvas | .//iframe | .//form | .//button'
            .' | .//nav | .//header | .//footer | .//aside'
            .' | .//*[contains(translate(concat(" ", normalize-space(@class), " ", normalize-space(@id), " "),'
            .'"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"), "related")]'
            .' | .//*[contains(translate(concat(" ", normalize-space(@class), " ", normalize-space(@id), " "),'
            .'"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"), "recommend")]'
            .' | .//*[contains(translate(concat(" ", normalize-space(@class), " ", normalize-space(@id), " "),'
            .'"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"), "share")]'
            .' | .//*[contains(translate(concat(" ", normalize-space(@class), " ", normalize-space(@id), " "),'
            .'"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"), "comment")]'
            .' | .//*[contains(translate(concat(" ", normalize-space(@class), " ", normalize-space(@id), " "),'
            .'"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"), "subscribe")]'
            .' | .//*[contains(translate(concat(" ", normalize-space(@class), " ", normalize-space(@id), " "),'
            .'"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"), "advert")]',
            $root,
        );
        $remove = [];
        foreach ($unwanted ?: [] as $unwantedNode) {
            $remove[] = $unwantedNode;
        }
        foreach (array_reverse($remove) as $unwantedNode) {
            $unwantedNode->parentNode?->removeChild($unwantedNode);
        }

        $parts = [];
        $this->appendText($root, $parts);

        return implode('', $parts);
    }

    /**
     * Рекурсивно собирает текст DOM, добавляя переносы после абзацев и других
     * блочных элементов, чтобы полное описание оставалось читаемым.
     *
     * @param  list<string>  $parts
     */
    private function appendText(DOMNode $node, array &$parts): void
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $value = $node->nodeValue ?? '';
            if (trim($value) !== '' || ! str_contains($value, "\n")) {
                $parts[] = $value;
            }

            return;
        }

        if (strtolower($node->nodeName) === 'br') {
            $parts[] = "\n";

            return;
        }

        foreach ($node->childNodes as $child) {
            $this->appendText($child, $parts);
        }

        if (in_array(strtolower($node->nodeName), ['p', 'li', 'blockquote', 'h2', 'h3', 'h4', 'h5', 'h6', 'section', 'div'], true)) {
            $parts[] = "\n";
        }
    }

    /**
     * Извлекает лид или подзаголовок из распространённых семантических CSS-блоков статьи.
     */
    private function lead(DOMXPath $xpath): string
    {
        $descriptor = sprintf(
            "translate(concat(' ', normalize-space(@class), ' ', normalize-space(@id), ' '),'%s','%s')",
            self::UPPERCASE,
            self::LOWERCASE,
        );
        $query = sprintf(
            "//*[contains(%1\$s,'announce') or contains(%1\$s,' lead ') or contains(%1\$s,'summary')"
            ." or contains(%1\$s,'subtitle') or contains(%1\$s,'description')]",
            $descriptor,
        );

        return $this->text($xpath, '('.$query.')[1]');
    }

    /**
     * Возвращает первый содержательный абзац внутри выбранного контейнера статьи.
     */
    private function firstParagraph(DOMXPath $xpath, ?DOMNode $contentNode): string
    {
        if ($contentNode !== null) {
            foreach ($xpath->query('.//p[normalize-space()]', $contentNode) ?: [] as $paragraph) {
                $value = $this->normalizer->copiedField($paragraph->textContent);
                if (mb_strlen($value) >= 30) {
                    return $value;
                }
            }
        }

        return $this->text($xpath, '(//h1/following::p[normalize-space()])[1]');
    }

    /**
     * Использует первый абзац уже очищенного полного текста как последний fallback
     * для страниц без отдельного краткого описания.
     */
    private function firstBodyParagraph(string $body): string
    {
        foreach (preg_split('/\n+/u', $body) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);
            if (mb_strlen($paragraph) >= 30) {
                return $paragraph;
            }
        }

        return '';
    }

    /**
     * Проверяет кандидата краткого описания и удаляет из него случайно добавленный
     * заголовок, название сайта и обрамляющую пунктуацию.
     *
     * @param  list<string>  $siteNames
     */
    private function cleanDescriptionCandidate(string $candidate, string $title, array $siteNames): ?string
    {
        $candidate = $this->normalizer->copiedField($candidate);
        if ($candidate === '') {
            return null;
        }

        if ($title !== '' && str_starts_with($candidate, $title)) {
            $candidate = trim(mb_substr($candidate, mb_strlen($title)), " \t\n\r\0\x0B—–-:|·");
        }

        $fingerprint = $this->fingerprint($candidate);
        if ($fingerprint === '' || $fingerprint === $this->fingerprint($title)) {
            return null;
        }
        foreach ($siteNames as $siteName) {
            if ($fingerprint === $this->fingerprint((string) $siteName)) {
                return null;
            }
        }
        if (preg_match('/^(главная|новости|официальный сайт|правительство россии)$/iu', $candidate)) {
            return null;
        }
        if (preg_match('/^(варианты поиска|поиск по сайту|введите запрос)/iu', $candidate)) {
            return null;
        }

        return mb_strlen($candidate) >= 12 ? $candidate : null;
    }

    /**
     * Создаёт регистронезависимое представление текста для сравнения заголовка,
     * описания и названия сайта без влияния пунктуации.
     */
    private function fingerprint(string $value): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($value)) ?? '';
    }

    /**
     * Находит первое строковое значение указанного поля в произвольной структуре JSON-LD.
     */
    private function jsonLdValue(DOMXPath $xpath, string $field): string
    {
        foreach ($xpath->query("//script[@type='application/ld+json']") ?: [] as $node) {
            $json = json_decode(trim($node->textContent), true);
            $value = $this->findJsonLdValue($json, $field);
            if ($value !== null) {
                return $value;
            }
        }

        return '';
    }

    /**
     * Рекурсивно обходит массив JSON-LD и возвращает непустое строковое поле.
     */
    private function findJsonLdValue(mixed $data, string $field): ?string
    {
        if (! is_array($data)) {
            return null;
        }
        if (isset($data[$field]) && is_string($data[$field]) && trim($data[$field]) !== '') {
            return $data[$field];
        }
        foreach ($data as $value) {
            if (($found = $this->findJsonLdValue($value, $field)) !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Ищет дату публикации сначала в JSON-LD, затем в стандартных метатегах и HTML-атрибутах.
     *
     * Такой порядок повышает точность и одновременно поддерживает страницы без schema.org-разметки.
     */
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

    /**
     * Рекурсивно находит поле `datePublished` внутри произвольной структуры JSON-LD.
     *
     * Рекурсия нужна для поддержки массивов графов и вложенных schema.org-объектов.
     */
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

    /**
     * Преобразует строковое представление даты в неизменяемое UTC-значение.
     *
     * Некорректная или пустая дата возвращается как `null`, позволяя продолжить поиск fallback-значения.
     */
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

    /**
     * Извлекает содержимое метатега по имени атрибута и его значению без учёта регистра.
     */
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

    /**
     * Возвращает значение HTML-атрибута первого элемента, соответствующего XPath-запросу.
     *
     * Пустая строка используется как безопасный результат при отсутствии элемента.
     */
    private function attribute(DOMXPath $xpath, string $query, string $attribute): string
    {
        $node = $xpath->query($query)?->item(0);

        return $node instanceof DOMElement ? trim($node->getAttribute($attribute)) : '';
    }

    /**
     * Возвращает очищенное текстовое содержимое первого узла XPath-запроса.
     */
    private function text(DOMXPath $xpath, string $query): string
    {
        return trim($xpath->query($query)?->item(0)?->textContent ?? '');
    }

    /**
     * Преобразует относительный URL ресурса в абсолютный относительно адреса статьи.
     *
     * Если URI невозможно разобрать, исходное значение сохраняется для последующей валидации.
     */
    private function resolveUrl(string $base, string $candidate): string
    {
        try {
            return (string) UriResolver::resolve(new Uri($base), new Uri(trim($candidate)));
        } catch (Throwable) {
            return $candidate;
        }
    }
}
