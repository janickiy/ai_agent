<?php

declare(strict_types=1);

namespace App\NewsMonitor\DTO;

use Carbon\CarbonImmutable;

final readonly class ExtractedArticle
{
    /** @param array<string, mixed> $meta */
    public function __construct(
        public string $canonicalUrl,
        public string $title,
        public string $description,
        public string $body,
        public ?string $imageUrl,
        public ?CarbonImmutable $publishedAt,
        public array $meta,
    ) {}
}
