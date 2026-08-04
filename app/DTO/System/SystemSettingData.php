<?php

declare(strict_types=1);

namespace App\DTO\System;

use App\DTO\DataTransferObject;

final readonly class SystemSettingData extends DataTransferObject
{
    /** @param array<string, mixed> $value */
    public function __construct(
        public string $key,
        public array  $value,
        public bool   $isSecret,
    )
    {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            key: (string)$data['key'],
            value: (array)($data['value'] ?? []),
            isSecret: (bool)($data['is_secret'] ?? false),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'is_secret' => $this->isSecret,
        ];
    }
}
