<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\DTO\Catalog\NewsCategoryData;
use App\Http\Controllers\Controller;
use App\Modules\NewsMonitor\Models\NewsCategory;
use App\Modules\NewsMonitor\Repositories\Catalog\NewsCategoryRepository;
use App\Modules\NewsMonitor\Services\AuditLogger;
use App\Modules\Admin\Requests\CategoryRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class CategoryController extends Controller
{
    public function __construct(
        private readonly NewsCategoryRepository $categories,
        private readonly AuditLogger            $audit,
    )
    {
    }

    /**
     * @return View
     */
    public function index(): View
    {
        return view('admin.categories.index');
    }

    /**
     * @return View
     */
    public function create(): View
    {
        Gate::authorize('manage-categories');

        return view('admin.categories.create');
    }

    /**
     * @param CategoryRequest $request
     * @return RedirectResponse
     * @throws \Throwable
     */
    public function store(CategoryRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $category = $this->categories->create(
                NewsCategoryData::fromArray($request->validated()),
            );
            $this->audit->record(
                $request->user()->id,
                'category.created',
                $category,
                $category->getKey(),
                null,
                $category->toArray(),
            );
        });

        return redirect()->route('admin.categories.index')->with('status', 'Тематика добавлена.');
    }

    /**
     * @param NewsCategory $category
     * @return View
     */
    public function edit(NewsCategory $category): View
    {
        Gate::authorize('manage-categories');

        return view('admin.categories.edit', ['category' => $category]);
    }

    /**
     * @param CategoryRequest $request
     * @param NewsCategory $category
     * @return RedirectResponse
     * @throws \Throwable
     */
    public function update(CategoryRequest $request, NewsCategory $category): RedirectResponse
    {
        DB::transaction(function () use ($request, $category): void {
            $before = $category->toArray();
            $updated = $this->categories->update(
                $category,
                NewsCategoryData::fromArray($request->validated()),
            );
            $this->audit->record(
                $request->user()->id,
                'category.updated',
                $updated,
                $updated->getKey(),
                $before,
                $updated->toArray(),
            );
        });

        return redirect()->route('admin.categories.index')->with('status', 'Тематика обновлена.');
    }

    /**
     * @param NewsCategory $category
     * @return RedirectResponse
     * @throws \Throwable
     */
    public function destroy(NewsCategory $category): RedirectResponse
    {
        Gate::authorize('manage-categories');

        DB::transaction(function () use ($category): void {
            $locked = $this->categories->lockForUpdate($category);
            if ($this->categories->isInUse($locked)) {
                throw ValidationException::withMessages([
                    'category' => 'Нельзя удалить тематику, которая уже используется в материалах, публикациях или подтемах. Её можно сделать неактивной.',
                ]);
            }

            $before = $this->categories->withSources($locked)->toArray();
            $this->categories->delete($locked);
            $this->audit->record(
                request()->user()->id,
                'category.deleted',
                $locked,
                $locked->getKey(),
                $before,
                null,
            );
        });

        return redirect()->route('admin.categories.index')->with('status', 'Тематика удалена.');
    }
}
