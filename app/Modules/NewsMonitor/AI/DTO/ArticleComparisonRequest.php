<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\AI\DTO;

final readonly class ArticleComparisonRequest
{
    public function __construct(public string $first, public string $second) {}
}
