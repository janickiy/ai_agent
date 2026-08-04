<?php

declare(strict_types=1);

namespace App\DTO\Pipeline;

use App\DTO\DataTransferObject;
use DateTimeInterface;

final readonly class ProcessingLogData extends DataTransferObject
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public string             $correlationId,
        public ?int               $sourceId,
        public ?int               $sourceItemId,
        public ?int               $publicationPostId,
        public string             $stage,
        public string             $status,
        public int                $attempt,
        public ?int               $durationMs,
        public ?string            $reasonCode,
        public ?string            $errorMessage,
        public array              $context,
        public DateTimeInterface  $startedAt,
        public ?DateTimeInterface $finishedAt,
    )
    {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'correlation_id' => $this->correlationId,
            'source_id' => $this->sourceId,
            'source_item_id' => $this->sourceItemId,
            'publication_post_id' => $this->publicationPostId,
            'stage' => $this->stage,
            'status' => $this->status,
            'attempt' => $this->attempt,
            'duration_ms' => $this->durationMs,
            'reason_code' => $this->reasonCode,
            'error_message' => $this->errorMessage,
            'context' => $this->context,
            'started_at' => $this->startedAt,
            'finished_at' => $this->finishedAt,
        ];
    }
}
