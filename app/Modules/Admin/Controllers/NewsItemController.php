<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSourceItem;
use App\NewsMonitor\Models\SourceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class NewsItemController extends Controller
{
    public function index(Request $request): View
    {
        $items = SourceItem::query()
            ->with(['source', 'analysis.category'])
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('discovered_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.items.index', compact('items'));
    }

    public function retry(SourceItem $item): RedirectResponse
    {
        Gate::authorize('operate-pipeline');
        ProcessSourceItem::dispatch($item->id);

        return back()->with('status', 'Повторная обработка поставлена в очередь.');
    }
}
