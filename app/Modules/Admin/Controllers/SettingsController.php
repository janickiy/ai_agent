<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Requests\SettingsRequest;
use App\NewsMonitor\Models\AuditLog;
use App\NewsMonitor\Models\SystemSetting;
use App\NewsMonitor\Services\AgentSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class SettingsController extends Controller
{
    public function edit(AgentSettings $settings): View
    {
        return view('admin.settings.edit', ['settings' => $settings->all()]);
    }

    public function update(SettingsRequest $request, AgentSettings $settings): RedirectResponse
    {
        $before = $settings->all();
        $validated = $request->validated();
        $values = [
            'collection_enabled' => (bool) $validated['collection_enabled'],
            'automatic_publication' => (bool) $validated['automatic_publication'],
            'max_news_age_hours' => (int) $validated['max_news_age_hours'],
            'event_similarity_threshold' => (float) $validated['event_similarity_threshold'],
        ];
        $setting = $settings->update($values);

        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'correlation_id' => (string) Str::uuid(),
            'action' => 'settings.updated',
            'entity_type' => SystemSetting::class,
            'entity_id' => (string) $setting->getKey(),
            'before' => $before,
            'after' => $values,
            'result' => 'success',
            'created_at' => now()->utc(),
        ]);

        return redirect()->route('admin.settings.edit')->with('status', 'Настройки сохранены.');
    }
}
