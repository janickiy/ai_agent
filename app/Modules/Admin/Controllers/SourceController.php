<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\DTO\Catalog\SourceData;
use App\DTO\Catalog\SourceStatusData;
use App\Http\Controllers\Controller;
use App\Modules\NewsMonitor\Models\Source;
use App\Modules\NewsMonitor\Repositories\Catalog\NewsCategoryRepository;
use App\Modules\NewsMonitor\Repositories\Catalog\SourceRepository;
use App\Modules\NewsMonitor\Services\AuditLogger;
use App\Modules\Admin\Requests\SourceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class SourceController extends Controller
{
    public function __construct(
        private readonly SourceRepository $sources,
        private readonly NewsCategoryRepository $categories,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return View
     */
    public function index(): View
    {
        return view('admin.sources.index');
    }

    /**
     * @return View
     */
    public function create(): View
    {
        Gate::authorize('manage-sources');

        return view('admin.sources.create', [
            'categories' => $this->categories->active(),
            'sourceClasses' => config('news_sources.classes', []),
        ]);
    }

    public function store(SourceRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $source = $this->sources->create(SourceData::fromArray($request->validated()));
            $after = $this->sources->withCategories($source)->toArray();
            $this->audit->record(
                $request->user()->id,
                'source.created',
                $source,
                $source->getKey(),
                null,
                $after,
            );
        });

        return redirect()->route('admin.sources.index')->with('status', 'Источник добавлен.');
    }

    public function edit(Source $source): View
    {
        Gate::authorize('manage-sources');

        return view('admin.sources.edit', [
            'source' => $this->sources->withCategories($source),
            'categories' => $this->categories->active(),
            'sourceClasses' => config('news_sources.classes', []),
        ]);
    }

    public function update(SourceRequest $request, Source $source): RedirectResponse
    {
        DB::transaction(function () use ($request, $source): void {
            $before = $this->sources->withCategories($source)->toArray();
            $updated = $this->sources->update($source, SourceData::fromArray($request->validated()));
            $after = $this->sources->withCategories($updated)->toArray();
            $this->audit->record(
                $request->user()->id,
                'source.updated',
                $updated,
                $updated->getKey(),
                $before,
                $after,
            );
        });

        return back()->with('status', 'Источник обновлён.');
    }

    public function toggle(Source $source): RedirectResponse
    {
        Gate::authorize('manage-sources');
        DB::transaction(function () use ($source): void {
            $before = $source->toArray();
            $updated = $this->sources->update(
                $source,
                SourceStatusData::fromArray(['is_active' => ! $source->is_active]),
            );
            $this->audit->record(
                request()->user()->id,
                'source.toggled',
                $updated,
                $updated->getKey(),
                $before,
                $updated->toArray(),
            );
        });

        return back()->with('status', 'Состояние источника изменено.');
    }

    public function destroy(Source $source): RedirectResponse
    {
        Gate::authorize('manage-sources');

        DB::transaction(function () use ($source): void {
            $locked = $this->sources->lockForUpdate($source);
            if ($this->sources->hasItems($locked)) {
                throw ValidationException::withMessages([
                    'source' => 'Нельзя удалить источник, к которому уже привязаны материалы. Сначала отключите его.',
                ]);
            }

            $before = $this->sources->withCategories($locked)->toArray();
            $this->sources->delete($locked);
            $this->audit->record(
                request()->user()->id,
                'source.deleted',
                $locked,
                $locked->getKey(),
                $before,
                null,
            );
        });

        return redirect()->route('admin.sources.index')->with('status', 'Источник удалён.');
    }
}
