<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\AI\DTO;

final readonly class EmbeddingResult
{
    /** @param list<float> $vector */
    public function __construct(
        public array $vector,
        public string $provider,
        public string $model,
    ) {}
}
