<?php

declare(strict_types=1);

namespace App\DTO\Admin;

use App\DTO\DataTransferObject;
use Illuminate\Support\Str;

/**
 * Переносит валидированные данные административной учётной записи между HTTP-слоем
 * и репозиторием, не позволяя форме управлять ролью и правами напрямую.
 */
final readonly class AdministratorData extends DataTransferObject
{
    /**
     * Хранит логин, необязательный новый пароль и состояние администратора.
     */
    public function __construct(
        public string $login,
        public ?string $password,
        public bool $isActive,
    ) {}

    /**
     * Создаёт DTO из валидированных данных формы и нормализует логин для поиска и входа.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $password = trim((string) ($data['password'] ?? ''));

        return new self(
            login: Str::lower(trim((string) $data['login'])),
            password: $password === '' ? null : $password,
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    /**
     * Возвращает разрешённые атрибуты модели и принудительно задаёт права администратора.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $attributes = [
            'login' => $this->login,
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
