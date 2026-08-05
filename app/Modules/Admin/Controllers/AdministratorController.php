<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\DTO\Admin\AdministratorData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Admin\Repositories\AdministratorRepository;
use App\Modules\Admin\Requests\AdministratorRequest;
use App\Modules\NewsMonitor\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AdministratorController extends Controller
{
    /**
     * Получает репозиторий учётных записей и сервис аудита, через которые контроллер
     * выполняет изменения без прямой работы с моделями и журналом аудита.
     */
    public function __construct(
        private readonly AdministratorRepository $administrators,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Открывает страницу управления администраторами после проверки соответствующего права.
     *
     * Сами строки списка загружаются через серверный DataTable-эндпоинт.
     */
    public function index(): View
    {
        Gate::authorize('manage-administrators');

        return view('admin.administrators.index');
    }

    /**
     * Отображает форму создания новой административной учётной записи.
     *
     * Проверка Gate не позволяет пользователям без права управления администраторами
     * получить доступ к форме.
     */
    public function create(): View
    {
        Gate::authorize('manage-administrators');

        return view('admin.administrators.create');
    }

    /**
     * Создаёт административную учётную запись из валидированного DTO и фиксирует
     * результат операции в журнале аудита в рамках одной транзакции.
     *
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

    /**
     * Отображает форму редактирования существующего администратора.
     *
     * Дополнительная проверка гарантирует, что переданная учётная запись действительно
     * относится к администраторам, а не к другому типу пользователя.
     */
    public function edit(User $administrator): View
    {
        Gate::authorize('manage-administrators');
        $this->ensureAdministrator($administrator);

        return view('admin.administrators.edit', ['administrator' => $administrator]);
    }

    /**
     * Обновляет административную учётную запись и записывает изменения в аудит.
     *
     * Метод запрещает отключать себя и последнего активного администратора,
     * чтобы не оставить систему без доступной административной учётной записи.
     */
    public function update(AdministratorRequest $request, User $administrator): RedirectResponse
    {
        $this->ensureAdministrator($administrator);
        $values = $request->validated();

        if ($request->user()->is($administrator) && ! $values['is_active']) {
            return back()->withErrors([
                'administrator' => 'Нельзя отключить собственную учетную запись.',
            ])->withInput();
        }

        DB::transaction(function () use ($request, $administrator, $values): void {
            $activeCount = $this->administrators->activeCountForUpdate();
            $locked = $this->administrators->lockForUpdate($administrator);
            if (
                $locked->is_active
                && ! $values['is_active']
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

    /**
     * Удаляет выбранного администратора и сохраняет прежнее состояние в журнале аудита.
     *
     * Собственная учётная запись и последний активный администратор защищены от удаления.
     */
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

    /**
     * Проверяет роль переданной учётной записи и возвращает HTTP 404 для пользователя,
     * который не является администратором.
     */
    private function ensureAdministrator(User $administrator): void
    {
        abort_unless($administrator->isAdministrator(), 404);
    }
}
