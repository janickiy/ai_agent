<?php

declare(strict_types=1);

namespace App\Modules\Admin\Requests;

use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Проверяет запрос массовой ручной публикации материалов из административной панели.
 *
 * Класс ограничивает количество записей, требует существующие уникальные идентификаторы
 * и разрешает операцию только пользователям с правом управления pipeline.
 */
final class ManualPublicationRequest extends FormRequest
{
    /**
     * Разрешает массовую публикацию администраторам и операторам pipeline.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('operate-pipeline') ?? false;
    }

    /**
     * Возвращает правила проверки массива выбранных материалов.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'item_ids' => ['required', 'array', 'min:1', 'max:500'],
            'item_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:'.NewsTables::name('source_items').',id',
            ],
        ];
    }

    /**
     * Возвращает понятные сообщения об ошибках выбора материалов.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'item_ids.required' => 'Выберите хотя бы один материал для публикации.',
            'item_ids.array' => 'Список материалов имеет неверный формат.',
            'item_ids.min' => 'Выберите хотя бы один материал для публикации.',
            'item_ids.max' => 'За один раз можно опубликовать не более 500 материалов.',
            'item_ids.*.integer' => 'Идентификатор материала должен быть целым числом.',
            'item_ids.*.distinct' => 'В списке обнаружены повторяющиеся материалы.',
            'item_ids.*.exists' => 'Один из выбранных материалов больше не существует.',
        ];
    }
}
