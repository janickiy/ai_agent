<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSourceItem;
use App\Modules\NewsMonitor\Models\SourceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class NewsItemController extends Controller
{
    /**
     * Открывает страницу найденных материалов.
     *
     * Содержимое таблицы загружается отдельным AJAX-запросом, поэтому метод отвечает
     * только за отображение административного интерфейса.
     *
     * @return View
     */
    public function index(): View
    {
        return view('admin.items.index');
    }

    /**
     * Повторно ставит выбранный материал в очередь анализа после проверки полномочий.
     *
     * Метод нужен для ручного восстановления обработки материала после временной ошибки.
     *
     * @param SourceItem $item
     * @return RedirectResponse
     */
    public function retry(SourceItem $item): RedirectResponse
    {
        Gate::authorize('operate-pipeline');
        ProcessSourceItem::dispatch($item->id);

        return back()->with('status', 'Повторная обработка поставлена в очередь.');
    }
}
