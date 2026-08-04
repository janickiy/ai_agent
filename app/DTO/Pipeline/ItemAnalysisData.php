<?php

declare(strict_types=1);

namespace App\DTO\Pipeline;

use App\DTO\DataTransferObject;
use DateTimeInterface;

final readonly class ItemAnalysisData extends DataTransferObject
{
    /**
     * @param list<string> $hashtags
     * @param list<string> $entities
     * @param array<string, mixed> $decisionMeta
     */
    public function __construct(
        public int               $sourceItemId,
        public ?int              $categoryId,
        public bool              $isActual,
        public float             $actualityScore,
        public bool              $isAdvertising,
        public float             $adConfidence,
        public float             $categoryConfidence,
        public array             $hashtags,
        public array             $entities,
        public string            $provider,
        public string            $model,
        public string            $promptVersion,
        public array             $decisionMeta,
        public DateTimeInterface $checkedAt,
    )
    {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'source_item_id' => $this->sourceItemId,
            'category_id' => $this->categoryId,
            'is_actual' => $this->isActual,
            'actuality_score' => $this->actualityScore,
            'is_advertising' => $this->isAdvertising,
            'ad_confidence' => $this->adConfidence,
            'category_confidence' => $this->categoryConfidence,
            'hashtags' => $this->hashtags,
            'entities' => $this->entities,
            'provider' => $this->provider,
            'model' => $this->model,
            'prompt_version' => $this->promptVersion,
            'decision_meta' => $this->decisionMeta,
            'checked_at' => $this->checkedAt,
        ];
    }
}
