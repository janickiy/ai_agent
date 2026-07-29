<?php

declare(strict_types=1);

namespace App\Modules\Admin\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class AdministratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-administrators') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $administrator = $this->route('administrator');
        $creating = ! $administrator instanceof User;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($creating ? null : $administrator->getKey()),
            ],
            'password' => [
                $creating ? 'required' : 'nullable',
                'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers(),
            ],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
