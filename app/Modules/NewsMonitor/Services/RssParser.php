<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Services;

use App\Modules\NewsMonitor\DTO\DiscoveredArticle;
use App\Modules\NewsMonitor\Exceptions\ExtractionException;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

final class RssParser
{
    /** @return list<DiscoveredArticle> */
    public function parse(string $xml): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            throw new ExtractionException('RSS/Atom document cannot be parsed.');
        }

        $xpath = new DOMXPath($document);
        $items = $xpath->query('//*[local-name()="item" or local-name()="entry"]');
        $results = [];

        foreach ($items ?: [] as $item) {
            if (! $item instanceof DOMElement) {
                continue;
            }
            $linkNode = $xpath->query('./*[local-name()="link"]', $item)?->item(0);
            $url = $linkNode instanceof DOMElement
                ? ($linkNode->getAttribute('href') ?: trim($linkNode->textContent))
                : '';
            if ($url === '') {
                continue;
            }
            $results[] = new DiscoveredArticle($url, [
                'title' => $this->childText($xpath, $item, 'title'),
                'description' => $this->childText($xpath, $item, 'description')
                    ?: $this->childText($xpath, $item, 'summary'),
                'published_at' => $this->childText($xpath, $item, 'pubDate')
                    ?: $this->childText($xpath, $item, 'published')
                    ?: $this->childText($xpath, $item, 'updated'),
                'feed_guid' => $this->childText($xpath, $item, 'guid')
                    ?: $this->childText($xpath, $item, 'id'),
            ]);
        }

        return $results;
    }

    private function childText(DOMXPath $xpath, DOMNode $node, string $name): string
    {
        return trim($xpath->query('./*[local-name()="'.$name.'"]', $node)?->item(0)?->textContent ?? '');
    }
}
