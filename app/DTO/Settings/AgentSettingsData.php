<?php

declare(strict_types=1);

namespace App\DTO\Settings;

use App\DTO\DataTransferObject;

final readonly class AgentSettingsData extends DataTransferObject
{
    public function __construct(
        public bool $automaticPublication,
        public int $maxNewsAgeHours,
        public float $eventSimilarityThreshold,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            automaticPublication: (bool) $data['automatic_publication'],
            maxNewsAgeHours: (int) $data['max_news_age_hours'],
            eventSimilarityThreshold: (float) $data['event_similarity_threshold'],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'automatic_publication' => $this->automaticPublication,
            'max_news_age_hours' => $this->maxNewsAgeHours,
            'event_similarity_threshold' => $this->eventSimilarityThreshold,
        ];
    }
}
