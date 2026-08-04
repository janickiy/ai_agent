<?php

declare(strict_types=1);

namespace App\DTO\Catalog;

use App\DTO\DataTransferObject;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class SourceStatusData extends DataTransferObject
{
    private const FIELDS = [
        'is_active',
        'status',
        'last_error',
        'last_success_at',
        'next_poll_at',
    ];

    private function __construct(
        public ?bool              $isActive,
        public ?string            $status,
        public ?string            $lastError,
        public ?DateTimeInterface $lastSuccessAt,
        public ?DateTimeInterface $nextPollAt,
        private bool              $hasIsActive,
        private bool              $hasStatus,
        private bool              $hasLastError,
        private bool              $hasLastSuccessAt,
        private bool              $hasNextPollAt,
    )
    {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (array_intersect(self::FIELDS, array_keys($data)) === []) {
            throw new InvalidArgumentException('Source status DTO must contain at least one status field.');
        }

        return new self(
            isActive: array_key_exists('is_active', $data) ? (bool)$data['is_active'] : null,
            status: array_key_exists('status', $data) ? (string)$data['status'] : null,
            lastError: array_key_exists('last_error', $data) && $data['last_error'] !== null
                ? (string)$data['last_error']
                : null,
            lastSuccessAt: self::dateTime($data['last_success_at'] ?? null),
            nextPollAt: self::dateTime($data['next_poll_at'] ?? null),
            hasIsActive: array_key_exists('is_active', $data),
            hasStatus: array_key_exists('status', $data),
            hasLastError: array_key_exists('last_error', $data),
            hasLastSuccessAt: array_key_exists('last_success_at', $data),
            hasNextPollAt: array_key_exists('next_poll_at', $data),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $attributes = [];

        if ($this->hasIsActive) {
            $attributes['is_active'] = $this->isActive;
        }
        if ($this->hasStatus) {
            $attributes['status'] = $this->status;
        }
        if ($this->hasLastError) {
            $attributes['last_error'] = $this->lastError;
        }
        if ($this->hasLastSuccessAt) {
            $attributes['last_success_at'] = $this->lastSuccessAt;
        }
        if ($this->hasNextPollAt) {
            $attributes['next_poll_at'] = $this->nextPollAt;
        }

        return $attributes;
    }

    private static function dateTime(mixed $value): ?DateTimeInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        return CarbonImmutable::parse((string)$value);
    }
}
