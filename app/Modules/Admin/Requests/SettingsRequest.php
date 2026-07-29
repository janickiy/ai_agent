<?php

declare(strict_types=1);

namespace App\Modules\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'collection_enabled' => ['boolean'],
            'automatic_publication' => ['boolean'],
            'max_news_age_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'event_similarity_threshold' => ['required', 'numeric', 'min:0', 'max:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'collection_enabled' => $this->boolean('collection_enabled'),
            'automatic_publication' => $this->boolean('automatic_publication'),
            'event_similarity_threshold' => str_replace(
                ',',
                '.',
                (string) $this->input('event_similarity_threshold'),
            ),
        ]);
    }
}
