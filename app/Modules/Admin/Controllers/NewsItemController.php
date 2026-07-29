<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSourceItem;
use App\NewsMonitor\Models\SourceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class NewsItemController extends Controller
{
    public function index(): View
    {
        return view('admin.items.index');
    }

    public function retry(SourceItem $item): RedirectResponse
    {
        Gate::authorize('operate-pipeline');
        ProcessSourceItem::dispatch($item->id);

        return back()->with('status', 'Повторная обработка поставлена в очередь.');
    }
}
