<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\AI\DTO;

final readonly class ArticleComparisonResult
{
    public function __construct(
        public float $similarity,
        public string $provider,
        public string $model,
    ) {}
}
