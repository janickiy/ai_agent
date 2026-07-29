<?php

declare(strict_types=1);

namespace App\NewsMonitor\AI\DTO;

final readonly class TextOperationResult
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public array $data,
        public string $provider,
        public string $model,
    ) {}
}
