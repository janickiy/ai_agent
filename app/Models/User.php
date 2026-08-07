<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Представляет учётную запись пользователя административной панели.
 *
 * Таблица `users` хранит уникальный логин, хеш пароля, роль, состояние активности
 * и признак доступа к административному интерфейсу.
 */
#[Fillable(['login', 'password', 'role', 'is_active', 'admin_access'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Преобразует пароль при записи и системные флаги при чтении модели.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'admin_access' => 'boolean',
        ];
    }

    /**
     * Определяет, обладает ли учётная запись ролью администратора.
     */
    public function isAdministrator(): bool
    {
        return $this->role === 'administrator';
    }

    /**
     * Определяет, может ли пользователь выполнять операторские действия с контентом.
     */
    public function canOperate(): bool
    {
        return in_array($this->role, ['administrator', 'operator'], true);
    }
}
