<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\AI\DTO;

final readonly class ArticleAnalysisResult
{
    /**
     * @param  list<string>  $hashtags
     * @param  list<string>  $entities
     */
    public function __construct(
        public ?string $categoryCode,
        public float $categoryConfidence,
        public bool $isAdvertising,
        public float $adConfidence,
        public array $hashtags,
        public array $entities,
        public string $reason,
        public string $provider,
        public string $model,
    ) {}
}
