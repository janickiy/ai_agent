<?php

declare(strict_types=1);

namespace App\DTO\Catalog;

use App\DTO\DataTransferObject;

final readonly class NewsCategoryData extends DataTransferObject
{
    /** @param list<string> $keywords */
    public function __construct(
        public string $name,
        public string $code,
        public string $hashtag,
        public array  $keywords,
        public bool   $isActive,
    )
    {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $keywords = array_values(array_map(
            static fn(mixed $keyword): string => trim((string)$keyword),
            (array)($data['keywords'] ?? []),
        ));

        return new self(
            name: trim((string)$data['name']),
            code: strtolower(trim((string)$data['code'])),
            hashtag: trim((string)$data['hashtag']),
            keywords: $keywords,
            isActive: (bool)($data['is_active'] ?? true),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'hashtag' => $this->hashtag,
            'keywords' => $this->keywords,
            'is_active' => $this->isActive,
        ];
    }
}
