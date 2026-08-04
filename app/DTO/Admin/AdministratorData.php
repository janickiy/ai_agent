<?php

declare(strict_types=1);

namespace App\DTO\Admin;

use App\DTO\DataTransferObject;

final readonly class AdministratorData extends DataTransferObject
{
    public function __construct(
        public string  $name,
        public string  $email,
        public ?string $password,
        public bool    $isActive,
    )
    {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $password = trim((string)($data['password'] ?? ''));

        return new self(
            name: trim((string)$data['name']),
            email: strtolower(trim((string)$data['email'])),
            password: $password === '' ? null : $password,
            isActive: (bool)($data['is_active'] ?? true),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $attributes = [
            'name' => $this->name,
            'email' => $this->email,
            'role' => 'administrator',
            'is_active' => $this->isActive,
            'admin_access' => true,
        ];

        if ($this->password !== null) {
            $attributes['password'] = $this->password;
        }

        return $attributes;
    }
}
