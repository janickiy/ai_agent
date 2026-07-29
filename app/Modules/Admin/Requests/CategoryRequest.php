<?php

declare(strict_types=1);

namespace App\Modules\Admin\Requests;

use App\NewsMonitor\Models\NewsCategory;
use App\NewsMonitor\Support\NewsTables;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-categories') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/',
                Rule::unique(NewsTables::name('categories'), 'code')
                    ->ignore($category instanceof NewsCategory ? $category->getKey() : null),
            ],
            'hashtag' => ['required', 'string', 'max:128', 'regex:/^#[\pL\pN_]+$/u'],
            'keywords' => ['required', 'array', 'min:1'],
            'keywords.*' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $code = trim((string) $this->input('code'));
        $hashtag = trim((string) $this->input('hashtag'));
        $keywords = $this->input('keywords', []);

        if ($code === '') {
            $code = Str::slug($name, '_');
            if ($code === '') {
                $code = 'category_'.substr(sha1($name), 0, 12);
            }
        }

        if ($hashtag === '') {
            $hashtag = '#'.preg_replace('/[^\pL\pN_]+/u', '', $name);
        } elseif (! str_starts_with($hashtag, '#')) {
            $hashtag = '#'.$hashtag;
        }

        if (is_string($keywords)) {
            $keywords = preg_split('/[\r\n,;]+/u', $keywords) ?: [];
        }

        $keywords = array_values(array_unique(array_filter(
            array_map(static fn (mixed $keyword): string => trim((string) $keyword), (array) $keywords),
            static fn (string $keyword): bool => $keyword !== '',
        )));

        $this->merge([
            'name' => $name,
            'code' => strtolower($code),
            'hashtag' => $hashtag,
            'keywords' => $keywords,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
