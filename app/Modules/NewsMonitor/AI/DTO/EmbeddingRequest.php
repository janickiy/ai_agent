<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\AI\DTO;

final readonly class EmbeddingRequest
{
    public function __construct(public string $text) {}
}
