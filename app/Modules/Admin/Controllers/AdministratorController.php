<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Admin\Requests\AdministratorRequest;
use App\NewsMonitor\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class AdministratorController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-administrators');

        return view('admin.administrators.index');
    }

    public function create(): View
    {
        Gate::authorize('manage-administrators');

        return view('admin.administrators.create');
    }

    public function store(AdministratorRequest $request): RedirectResponse
    {
        $administrator = User::query()->create([
            ...$request->validated(),
            'role' => 'administrator',
            'admin_access' => true,
        ]);
        $this->audit($request->user()->id, 'administrator.created', $administrator, null, $administrator->toArray());

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

        if ($request->user()->is($administrator) && ! $values['is_active']) {
            return back()->withErrors([
                'administrator' => 'Нельзя отключить собственную учетную запись.',
            ])->withInput();
        }

        if (
            $administrator->is_active
            && ! $values['is_active']
            && $this->activeAdministratorsCount() <= 1
        ) {
            return back()->withErrors([
                'administrator' => 'Нельзя отключить последнего активного администратора.',
            ])->withInput();
        }

        $before = $administrator->toArray();
        if (($values['password'] ?? '') === '') {
            $values = Arr::except($values, ['password']);
        }
        $administrator->update([
            ...$values,
            'role' => 'administrator',
            'admin_access' => true,
        ]);
        $this->audit(
            $request->user()->id,
            'administrator.updated',
            $administrator,
            $before,
            $administrator->fresh()->toArray(),
        );

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

        if ($administrator->is_active && $this->activeAdministratorsCount() <= 1) {
            return back()->withErrors([
                'administrator' => 'Нельзя удалить последнего активного администратора.',
            ]);
        }

        $before = $administrator->toArray();
        $administrator->delete();
        $this->audit($request->user()->id, 'administrator.deleted', $administrator, $before, null);

        return redirect()->route('admin.administrators.index')->with('status', 'Администратор удалён.');
    }

    private function ensureAdministrator(User $administrator): void
    {
        abort_unless($administrator->isAdministrator(), 404);
    }

    private function activeAdministratorsCount(): int
    {
        return User::query()
            ->where('role', 'administrator')
            ->where('is_active', true)
            ->where('admin_access', true)
            ->count();
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function audit(
        int $userId,
        string $action,
        User $administrator,
        ?array $before,
        ?array $after,
    ): void {
        AuditLog::query()->create([
            'user_id' => $userId,
            'correlation_id' => (string) Str::uuid(),
            'action' => $action,
            'entity_type' => User::class,
            'entity_id' => (string) $administrator->id,
            'before' => $before,
            'after' => $after,
            'result' => 'success',
            'created_at' => now()->utc(),
        ]);
    }
}
