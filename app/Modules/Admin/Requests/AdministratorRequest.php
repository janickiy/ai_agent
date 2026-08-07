<?php

declare(strict_types=1);

namespace App\Modules\Admin\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Проверяет данные создания и редактирования административной учётной записи.
 */
final class AdministratorRequest extends FormRequest
{
    /**
     * Разрешает запрос только пользователю с правом управления администраторами.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-administrators') ?? false;
    }

    /**
     * Задаёт правила уникальности логина, сложности пароля и состояния учётной записи.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $administrator = $this->route('administrator');
        $creating = ! $administrator instanceof User;

        return [
            'login' => [
                'required',
                'string',
                'min:3',
                'max:64',
                'regex:/\A[\pL\pN._-]+\z/u',
                Rule::unique('users', 'login')->ignore($creating ? null : $administrator->getKey()),
            ],
            'password' => [
                $creating ? 'required' : 'nullable',
                'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers(),
            ],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Приводит логин к нижнему регистру и преобразует переключатель активности в boolean.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'login' => Str::lower(trim((string) $this->input('login'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * Возвращает понятные подписи полей для сообщений об ошибках формы.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'login' => 'логин',
            'password' => 'пароль',
        ];
    }

    /**
     * Поясняет допустимый формат логина непосредственно в сообщении валидации.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.regex' => 'Логин может содержать только буквы, цифры, точки, дефисы и символы подчёркивания.',
        ];
    }
}
