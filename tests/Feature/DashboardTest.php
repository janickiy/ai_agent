<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\NewsMonitor\Models\ProcessingLog;
use App\Modules\NewsMonitor\Models\Source;
use App\Modules\NewsMonitor\Models\SourceItem;
use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_overview_agent_state_and_latest_events(): void
    {
        $this->seed();
        $user = User::factory()->create([
            'role' => 'administrator',
            'is_active' => true,
            'admin_access' => true,
        ]);
        $source = Source::query()->firstOrFail();
        $item = SourceItem::query()->create([
            'source_id' => $source->id,
            'discovery_url' => 'https://example.test/news/dashboard',
            'canonical_url' => 'https://example.test/news/dashboard',
            'canonical_url_hash' => hash('sha256', 'https://example.test/news/dashboard'),
            'title_original' => 'Новый транспортный объект',
            'status' => 'discovered',
            'discovered_at' => now()->utc(),
        ]);
        ProcessingLog::query()->create([
            'correlation_id' => (string) Str::uuid(),
            'source_id' => $source->id,
            'source_item_id' => $item->id,
            'stage' => 'fetch',
            'status' => 'error',
            'attempt' => 1,
            'context' => [],
            'started_at' => now()->utc(),
            'finished_at' => now()->utc(),
        ]);
        $eventId = DB::table(NewsTables::name('events'))->insertGetId([
            'fingerprint' => 'dashboard-event',
            'title' => 'Открытие нового транспортного объекта',
            'event_at' => now()->utc(),
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
        DB::table(NewsTables::name('event_items'))->insert([
            'news_event_id' => $eventId,
            'source_item_id' => $item->id,
            'similarity' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertSee('Панель управления')
            ->assertSee('Найдено сегодня')
            ->assertSee('На проверке')
            ->assertSee('Открытие нового транспортного объекта')
            ->assertSee($source->name)
            ->assertSee('Состояние агента')
            ->assertSee('deduplication');

        $response->assertViewHas('metrics', static function (array $metrics): bool {
            $values = collect($metrics)->pluck('value', 'label');

            return $values['Найдено сегодня'] === 1
                && $values['На проверке'] === 1
                && $values['Ошибки'] === 1;
        });
    }
}
