<?php

declare(strict_types=1);

namespace App\NewsMonitor\DTO;

final readonly class DiscoveredArticle
{
    /** @param array<string, mixed> $meta */
    public function __construct(public string $url, public array $meta = []) {}
}
