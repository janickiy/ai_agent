<?php

declare(strict_types=1);

namespace App\DTO\System;

use App\DTO\DataTransferObject;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;

final readonly class AuditLogData extends DataTransferObject
{
    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function __construct(
        public ?int              $userId,
        public string            $correlationId,
        public string            $action,
        public string            $entityType,
        public ?string           $entityId,
        public ?array            $before,
        public ?array            $after,
        public string            $result,
        public DateTimeInterface $createdAt,
    )
    {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: isset($data['user_id']) ? (int)$data['user_id'] : null,
            correlationId: (string)($data['correlation_id'] ?? Str::uuid()),
            action: (string)$data['action'],
            entityType: (string)$data['entity_type'],
            entityId: isset($data['entity_id']) ? (string)$data['entity_id'] : null,
            before: isset($data['before']) ? (array)$data['before'] : null,
            after: isset($data['after']) ? (array)$data['after'] : null,
            result: (string)($data['result'] ?? 'success'),
            createdAt: self::dateTime($data['created_at'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'correlation_id' => $this->correlationId,
            'action' => $this->action,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'before' => $this->before,
            'after' => $this->after,
            'result' => $this->result,
            'created_at' => $this->createdAt,
        ];
    }

    private static function dateTime(mixed $value): DateTimeInterface
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        return $value === null || $value === ''
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::parse((string)$value);
    }
}
