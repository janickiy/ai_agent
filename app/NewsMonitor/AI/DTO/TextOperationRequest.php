<?php

declare(strict_types=1);

namespace App\NewsMonitor\AI\DTO;

final readonly class TextOperationRequest
{
    /** @param array<string, mixed> $payload */
    public function __construct(public array $payload) {}
}
