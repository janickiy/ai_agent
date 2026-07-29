<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Requests\SourceRequest;
use App\NewsMonitor\Models\AuditLog;
use App\NewsMonitor\Models\NewsCategory;
use App\NewsMonitor\Models\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class SourceController extends Controller
{
    public function index(): View
    {
        return view('admin.sources.index');
    }

    public function create(): View
    {
        Gate::authorize('manage-sources');

        return view('admin.sources.create', [
            'categories' => NewsCategory::query()->where('is_active', true)->orderBy('id')->get(),
            'sourceClasses' => config('news_sources.classes', []),
        ]);
    }

    public function store(SourceRequest $request): RedirectResponse
    {
        $source = Source::query()->create(Arr::except($request->validated(), ['category_ids']));
        $source->categories()->sync($request->validated('category_ids', []));
        $this->audit($request->user()->id, 'source.created', $source, null, $source->fresh()->toArray());

        return redirect()->route('admin.sources.index')->with('status', 'Источник добавлен.');
    }

    public function edit(Source $source): View
    {
        Gate::authorize('manage-sources');

        return view('admin.sources.edit', [
            'source' => $source->load('categories'),
            'categories' => NewsCategory::query()->where('is_active', true)->orderBy('id')->get(),
            'sourceClasses' => config('news_sources.classes', []),
        ]);
    }

    public function update(SourceRequest $request, Source $source): RedirectResponse
    {
        $before = $source->toArray();
        $source->update(Arr::except($request->validated(), ['category_ids']));
        $source->categories()->sync($request->validated('category_ids', []));
        $this->audit($request->user()->id, 'source.updated', $source, $before, $source->fresh()->toArray());

        return back()->with('status', 'Источник обновлён.');
    }

    public function toggle(Source $source): RedirectResponse
    {
        Gate::authorize('manage-sources');
        $before = $source->toArray();
        $source->update(['is_active' => ! $source->is_active]);
        $this->audit(request()->user()->id, 'source.toggled', $source, $before, $source->fresh()->toArray());

        return back()->with('status', 'Состояние источника изменено.');
    }

    public function destroy(Source $source): RedirectResponse
    {
        Gate::authorize('manage-sources');

        if ($source->items()->exists()) {
            return back()->withErrors([
                'source' => 'Нельзя удалить источник, к которому уже привязаны материалы. Сначала отключите его.',
            ]);
        }

        $before = $source->load('categories')->toArray();
        $source->delete();
        $this->audit(request()->user()->id, 'source.deleted', $source, $before, null);

        return redirect()->route('admin.sources.index')->with('status', 'Источник удалён.');
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function audit(int $userId, string $action, Source $source, ?array $before, ?array $after): void
    {
        AuditLog::query()->create([
            'user_id' => $userId,
            'correlation_id' => (string) Str::uuid(),
            'action' => $action,
            'entity_type' => Source::class,
            'entity_id' => (string) $source->id,
            'before' => $before,
            'after' => $after,
            'result' => 'success',
            'created_at' => now()->utc(),
        ]);
    }
}
