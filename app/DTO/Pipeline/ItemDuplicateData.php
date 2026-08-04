<?php

declare(strict_types=1);

namespace App\DTO\Pipeline;

use App\DTO\DataTransferObject;

final readonly class ItemDuplicateData extends DataTransferObject
{
    /** @param array<string, mixed> $meta */
    public function __construct(
        public int    $sourceItemId,
        public int    $originalSourceItemId,
        public string $method,
        public float  $similarity,
        public string $algorithmVersion,
        public array  $meta,
    )
    {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'source_item_id' => $this->sourceItemId,
            'original_source_item_id' => $this->originalSourceItemId,
            'method' => $this->method,
            'similarity' => $this->similarity,
            'algorithm_version' => $this->algorithmVersion,
            'meta' => $this->meta,
        ];
    }
}
