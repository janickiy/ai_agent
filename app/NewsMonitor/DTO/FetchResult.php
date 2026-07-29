<?php

declare(strict_types=1);

namespace App\NewsMonitor\DTO;

final readonly class FetchResult
{
    /** @param array<string, string> $headers */
    public function __construct(
        public string $url,
        public string $body,
        public array $headers,
        public int $status,
    ) {}
}
