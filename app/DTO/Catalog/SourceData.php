<?php

declare(strict_types=1);

namespace App\DTO\Catalog;

use App\DTO\DataTransferObject;

final readonly class SourceData extends DataTransferObject
{
    /** @param list<int> $categoryIds */
    public function __construct(
        public string  $name,
        public string  $domain,
        public string  $type,
        public string  $sourceClass,
        public int     $trustScore,
        public string  $baseUrl,
        public ?string $feedUrl,
        public bool    $isActive,
        public bool    $isTrusted,
        public bool    $isAllowed,
        public int     $pollIntervalMinutes,
        public int     $requestLimit,
        public int     $timeoutSeconds,
        public int     $maxAttempts,
        public array   $categoryIds,
    )
    {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $feedUrl = trim((string)($data['feed_url'] ?? ''));
        $categoryIds = array_values(array_unique(array_map(
            static fn(mixed $id): int => (int)$id,
            (array)($data['category_ids'] ?? []),
        )));

        return new self(
            name: trim((string)$data['name']),
            domain: strtolower(trim((string)$data['domain'])),
            type: trim((string)$data['type']),
            sourceClass: trim((string)$data['source_class']),
            trustScore: (int)$data['trust_score'],
            baseUrl: trim((string)$data['base_url']),
            feedUrl: $feedUrl === '' ? null : $feedUrl,
            isActive: (bool)($data['is_active'] ?? false),
            isTrusted: (bool)($data['is_trusted'] ?? false),
            isAllowed: (bool)($data['is_allowed'] ?? false),
            pollIntervalMinutes: (int)$data['poll_interval_minutes'],
            requestLimit: (int)$data['request_limit'],
            timeoutSeconds: (int)$data['timeout_seconds'],
            maxAttempts: (int)$data['max_attempts'],
            categoryIds: $categoryIds,
        );
    }

    /** @return list<int> */
    public function categoryIds(): array
    {
        return $this->categoryIds;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'domain' => $this->domain,
            'type' => $this->type,
            'source_class' => $this->sourceClass,
            'trust_score' => $this->trustScore,
            'base_url' => $this->baseUrl,
            'feed_url' => $this->feedUrl,
            'is_active' => $this->isActive,
            'is_trusted' => $this->isTrusted,
            'is_allowed' => $this->isAllowed,
            'poll_interval_minutes' => $this->pollIntervalMinutes,
            'request_limit' => $this->requestLimit,
            'timeout_seconds' => $this->timeoutSeconds,
            'max_attempts' => $this->maxAttempts,
        ];
    }
}
