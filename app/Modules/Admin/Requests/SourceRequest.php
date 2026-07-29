<?php

declare(strict_types=1);

namespace App\Modules\Admin\Requests;

use App\NewsMonitor\Support\NewsTables;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-sources') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:190'],
            'type' => ['required', Rule::in(['rss', 'atom'])],
            'source_class' => ['required', Rule::in(array_keys(config('news_sources.classes', [])))],
            'trust_score' => ['required', 'integer', 'min:0', 'max:100'],
            'base_url' => ['required', 'url:http,https'],
            'feed_url' => ['nullable', 'url:http,https', 'max:512'],
            'is_active' => ['sometimes', 'boolean'],
            'is_trusted' => ['sometimes', 'boolean'],
            'is_allowed' => ['sometimes', 'boolean'],
            'poll_interval_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'request_limit' => ['required', 'integer', 'min:1', 'max:500'],
            'timeout_seconds' => ['required', 'integer', 'min:1', 'max:120'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:'.NewsTables::name('categories').',id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_trusted' => $this->boolean('is_trusted'),
            'is_allowed' => $this->boolean('is_allowed'),
        ]);
    }
}
