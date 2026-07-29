<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\RunNewsMonitor;
use App\NewsMonitor\Models\AuditLog;
use App\NewsMonitor\Models\SystemSetting;
use App\NewsMonitor\Services\AgentSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class ParserControlController extends Controller
{
    public function start(Request $request, AgentSettings $settings): RedirectResponse
    {
        Gate::authorize('operate-pipeline');

        $this->setCollectionState($request, $settings, true, 'parser.started');
        RunNewsMonitor::dispatch();

        return back()->with(
            'status',
            'Парсер запущен: сбор включён, внеплановая проверка источников добавлена в очередь.',
        );
    }

    public function stop(Request $request, AgentSettings $settings): RedirectResponse
    {
        Gate::authorize('operate-pipeline');

        $this->setCollectionState($request, $settings, false, 'parser.stopped');

        return back()->with('status', 'Парсер остановлен: новые циклы сбора отключены.');
    }

    private function setCollectionState(
        Request $request,
        AgentSettings $settings,
        bool $enabled,
        string $action,
    ): void {
        $before = $settings->all();
        $after = [...$before, 'collection_enabled' => $enabled];
        $setting = $settings->update($after);

        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'correlation_id' => (string) Str::uuid(),
            'action' => $action,
            'entity_type' => SystemSetting::class,
            'entity_id' => (string) $setting->getKey(),
            'before' => $before,
            'after' => $after,
            'result' => 'success',
            'created_at' => now()->utc(),
        ]);
    }
}
