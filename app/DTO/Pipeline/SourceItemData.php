<?php

declare(strict_types=1);

namespace App\DTO\Pipeline;

use App\DTO\DataTransferObject;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class SourceItemData extends DataTransferObject
{
    /** @var list<string> */
    private const FIELDS = [
        'source_id',
        'discovery_url',
        'canonical_url',
        'canonical_url_hash',
        'title_original',
        'description_original',
        'body_text',
        'image_url',
        'source_published_at',
        'title_description_hash',
        'content_hash',
        'status',
        'rejection_reason',
        'extraction_meta',
        'discovered_at',
        'fetched_at',
        'extracted_at',
        'analyzed_at',
    ];

    /** @param non-empty-array<string, mixed> $attributes */
    private function __construct(private array $attributes) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if ($data === []) {
            throw new InvalidArgumentException('Source item DTO payload cannot be empty.');
        }

        $unknown = array_diff(array_keys($data), self::FIELDS);
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'Unknown source item DTO fields: %s.',
                implode(', ', $unknown),
            ));
        }

        $attributes = [];
        foreach ($data as $field => $value) {
            $attributes[$field] = match ($field) {
                'source_id' => (int) $value,
                'source_published_at',
                'discovered_at',
                'fetched_at',
                'extracted_at',
                'analyzed_at' => self::dateTime($value),
                'extraction_meta' => $value === null ? null : (array) $value,
                'title_original',
                'description_original',
                'body_text',
                'image_url',
                'title_description_hash',
                'content_hash',
                'rejection_reason' => $value === null ? null : (string) $value,
                default => (string) $value,
            };
        }

        /** @var non-empty-array<string, mixed> $attributes */
        return new self($attributes);
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->attributes);
    }

    /** @param non-empty-list<string> $fields */
    public function requireFields(array $fields): void
    {
        $missing = array_values(array_filter(
            $fields,
            fn (string $field): bool => ! $this->has($field)
                || $this->attributes[$field] === null
                || $this->attributes[$field] === '',
        ));

        if ($missing !== []) {
            throw new InvalidArgumentException(sprintf(
                'Source item DTO is missing required fields: %s.',
                implode(', ', $missing),
            ));
        }
    }

    /** @return non-empty-array<string, mixed> */
    public function toArray(): array
    {
        return $this->attributes;
    }

    private static function dateTime(mixed $value): ?DateTimeInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        return CarbonImmutable::parse((string) $value);
    }
}
