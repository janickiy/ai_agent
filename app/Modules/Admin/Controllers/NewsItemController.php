<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSourceItem;
use App\Modules\Admin\Requests\ManualPublicationRequest;
use App\Modules\NewsMonitor\Models\SourceItem;
use App\Modules\NewsMonitor\Repositories\Pipeline\SourceItemRepository;
use App\Modules\NewsMonitor\Services\KaboomPublicationQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Управляет просмотром, повторной обработкой и ручной публикацией найденных материалов.
 *
 * Контроллер не публикует данные синхронно: повторную обработку направляет в анализ,
 * а проверенные одиночные или массовые материалы — сразу в очередь Kaboom.
 */
final class NewsItemController extends Controller
{
    /**
     * Получает репозиторий выборки и сервис безопасной постановки публикаций в очередь.
     */
    public function __construct(
        private readonly SourceItemRepository $sourceItems,
        private readonly KaboomPublicationQueue $publicationQueue,
    ) {}

    /**
     * Открывает страницу найденных материалов.
     *
     * Содержимое таблицы загружается отдельным AJAX-запросом, поэтому метод отвечает
     * только за отображение административного интерфейса.
     */
    public function index(): View
    {
        return view('admin.items.index');
    }

    /**
     * Повторно ставит выбранный материал в очередь анализа после проверки полномочий.
     *
     * Метод нужен для ручного восстановления обработки материала после временной ошибки.
     */
    public function retry(SourceItem $item): RedirectResponse
    {
        Gate::authorize('operate-pipeline');
        ProcessSourceItem::dispatch($item->id);

        return back()->with('status', 'Повторная обработка поставлена в очередь.');
    }

    /**
     * Ставит один проверенный материал в очередь ручной отправки публикации в Kaboom.
     *
     * Операция доступна только уже проверенному материалу, остановленному выключенным
     * автоматическим режимом. Сохранённые поля отправляются без повторного парсинга и AI.
     *
     * @throws ValidationException
     */
    public function publish(SourceItem $item): RedirectResponse
    {
        Gate::authorize('operate-pipeline');

        if (! $item->isAwaitingManualPublication()) {
            throw ValidationException::withMessages([
                'item' => 'Материал недоступен для ручной публикации или уже был опубликован.',
            ]);
        }

        if (! $this->publicationQueue->enqueue($item, (string) Str::uuid())) {
            throw ValidationException::withMessages([
                'item' => 'Состояние материала изменилось. Обновите таблицу и повторите действие.',
            ]);
        }

        return back()->with('status', 'Материал поставлен в очередь ручной публикации.');
    }

    /**
     * Ставит выбранные проверенные материалы в очередь ручной публикации.
     *
     * Репозиторий отбрасывает записи с недопустимым статусом. Пользователь получает
     * количество поставленных в очередь и пропущенных материалов.
     *
     * @throws ValidationException
     */
    public function publishMany(ManualPublicationRequest $request): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = array_map('intval', $request->validated('item_ids'));
        $items = $this->sourceItems->findManyAwaitingManualPublication($ids);

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'item_ids' => 'Выбранные материалы недоступны для ручной публикации.',
            ]);
        }

        $queued = 0;
        foreach ($items as $item) {
            if ($this->publicationQueue->enqueue($item, (string) Str::uuid())) {
                $queued++;
            }
        }

        $skipped = count($ids) - $queued;
        $message = "Материалы поставлены в очередь ручной публикации: {$queued}.";

        if ($skipped > 0) {
            $message .= " Пропущено: {$skipped}.";
        }

        return back()->with('status', $message);
    }
}
