<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Requests\CategoryRequest;
use App\NewsMonitor\Models\AuditLog;
use App\NewsMonitor\Models\NewsCategory;
use App\NewsMonitor\Support\NewsTables;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => NewsCategory::query()
                ->withCount('sources')
                ->orderBy('id')
                ->paginate(30)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manage-categories');

        return view('admin.categories.create');
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = NewsCategory::query()->create($request->validated());
        $this->audit($request->user()->id, 'category.created', $category, null, $category->toArray());

        return redirect()->route('admin.categories.index')->with('status', 'Тематика добавлена.');
    }

    public function edit(NewsCategory $category): View
    {
        Gate::authorize('manage-categories');

        return view('admin.categories.edit', ['category' => $category]);
    }

    public function update(CategoryRequest $request, NewsCategory $category): RedirectResponse
    {
        $before = $category->toArray();
        $category->update($request->validated());
        $this->audit($request->user()->id, 'category.updated', $category, $before, $category->fresh()->toArray());

        return redirect()->route('admin.categories.index')->with('status', 'Тематика обновлена.');
    }

    public function destroy(NewsCategory $category): RedirectResponse
    {
        Gate::authorize('manage-categories');

        if (
            $category->analyses()->exists()
            || $category->publicationPosts()->exists()
            || DB::table(NewsTables::name('subjects'))->where('category_id', $category->id)->exists()
        ) {
            return back()->withErrors([
                'category' => 'Нельзя удалить тематику, которая уже используется в материалах, публикациях или подтемах. Её можно сделать неактивной.',
            ]);
        }

        $before = $category->load('sources')->toArray();
        $category->delete();
        $this->audit(request()->user()->id, 'category.deleted', $category, $before, null);

        return redirect()->route('admin.categories.index')->with('status', 'Тематика удалена.');
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function audit(
        int $userId,
        string $action,
        NewsCategory $category,
        ?array $before,
        ?array $after,
    ): void {
        AuditLog::query()->create([
            'user_id' => $userId,
            'correlation_id' => (string) Str::uuid(),
            'action' => $action,
            'entity_type' => NewsCategory::class,
            'entity_id' => (string) $category->id,
            'before' => $before,
            'after' => $after,
            'result' => 'success',
            'created_at' => now()->utc(),
        ]);
    }
}
