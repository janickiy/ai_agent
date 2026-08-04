<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\DTO\Admin\AdministratorData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\NewsMonitor\Services\AuditLogger;
use App\Modules\Admin\Repositories\AdministratorRepository;
use App\Modules\Admin\Requests\AdministratorRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AdministratorController extends Controller
{
    public function __construct(
        private readonly AdministratorRepository $administrators,
        private readonly AuditLogger             $audit,
    )
    {
    }

    /**
     * @return View
     */
    public function index(): View
    {
        Gate::authorize('manage-administrators');

        return view('admin.administrators.index');
    }

    /**
     * @return View
     */
    public function create(): View
    {
        Gate::authorize('manage-administrators');

        return view('admin.administrators.create');
    }

    /**
     * @param AdministratorRequest $request
     * @return RedirectResponse
     * @throws \Throwable
     */
    public function store(AdministratorRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $administrator = $this->administrators->create(
                AdministratorData::fromArray($request->validated()),
            );
            $this->audit->record(
                $request->user()->id,
                'administrator.created',
                $administrator,
                $administrator->getKey(),
                null,
                $administrator->toArray(),
            );
        });

        return redirect()->route('admin.administrators.index')->with('status', 'Администратор добавлен.');
    }

    public function edit(User $administrator): View
    {
        Gate::authorize('manage-administrators');
        $this->ensureAdministrator($administrator);

        return view('admin.administrators.edit', ['administrator' => $administrator]);
    }

    public function update(AdministratorRequest $request, User $administrator): RedirectResponse
    {
        $this->ensureAdministrator($administrator);
        $values = $request->validated();

        if ($request->user()->is($administrator) && !$values['is_active']) {
            return back()->withErrors([
                'administrator' => 'Нельзя отключить собственную учетную запись.',
            ])->withInput();
        }

        DB::transaction(function () use ($request, $administrator, $values): void {
            $activeCount = $this->administrators->activeCountForUpdate();
            $locked = $this->administrators->lockForUpdate($administrator);
            if (
                $locked->is_active
                && !$values['is_active']
                && $activeCount <= 1
            ) {
                throw ValidationException::withMessages([
                    'administrator' => 'Нельзя отключить последнего активного администратора.',
                ]);
            }

            $before = $locked->toArray();
            $updated = $this->administrators->update(
                $locked,
                AdministratorData::fromArray($values),
            );
            $this->audit->record(
                $request->user()->id,
                'administrator.updated',
                $updated,
                $updated->getKey(),
                $before,
                $updated->toArray(),
            );
        });

        return redirect()->route('admin.administrators.index')->with('status', 'Администратор обновлён.');
    }

    public function destroy(Request $request, User $administrator): RedirectResponse
    {
        Gate::authorize('manage-administrators');
        $this->ensureAdministrator($administrator);

        if ($request->user()->is($administrator)) {
            return back()->withErrors([
                'administrator' => 'Нельзя удалить собственную учетную запись.',
            ]);
        }

        DB::transaction(function () use ($request, $administrator): void {
            $activeCount = $this->administrators->activeCountForUpdate();
            $locked = $this->administrators->lockForUpdate($administrator);
            if ($locked->is_active && $activeCount <= 1) {
                throw ValidationException::withMessages([
                    'administrator' => 'Нельзя удалить последнего активного администратора.',
                ]);
            }

            $before = $locked->toArray();
            $this->administrators->delete($locked);
            $this->audit->record(
                $request->user()->id,
                'administrator.deleted',
                $locked,
                $locked->getKey(),
                $before,
                null,
            );
        });

        return redirect()->route('admin.administrators.index')->with('status', 'Администратор удалён.');
    }

    private function ensureAdministrator(User $administrator): void
    {
        abort_unless($administrator->isAdministrator(), 404);
    }
}
