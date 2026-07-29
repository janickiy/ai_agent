<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\NewsMonitor\Contracts\HttpFetcher;
use App\NewsMonitor\Models\AuditLog;
use App\NewsMonitor\Models\Source;
use App\NewsMonitor\Models\SystemSetting;
use App\NewsMonitor\Services\AgentSettings;
use App\NewsMonitor\Services\SourceMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class SettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_view_and_update_agent_settings(): void
    {
        $administrator = User::factory()->create([
            'role' => 'administrator',
            'is_active' => true,
            'admin_access' => true,
        ]);

        $this->actingAs($administrator)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Настройки агента')
            ->assertSee('Сбор и обработка включены')
            ->assertSee('Сохранить');

        $this->actingAs($administrator)
            ->put('/admin/settings', [
                'collection_enabled' => false,
                'automatic_publication' => true,
                'max_news_age_hours' => 72,
                'event_similarity_threshold' => '0,72',
            ])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('status', 'Настройки сохранены.');

        $stored = SystemSetting::query()->findOrFail('agent');
        self::assertSame([
            'collection_enabled' => false,
            'automatic_publication' => true,
            'max_news_age_hours' => 72,
            'event_similarity_threshold' => 0.72,
        ], $stored->value);

        $settings = app(AgentSettings::class);
        self::assertFalse($settings->collectionEnabled());
        self::assertTrue($settings->automaticPublication());
        self::assertSame(72, $settings->maxNewsAgeHours());
        self::assertSame(0.72, $settings->eventSimilarityThreshold());
        self::assertSame(1, AuditLog::query()->where('action', 'settings.updated')->count());
    }

    public function test_viewer_can_see_settings_but_cannot_update_them(): void
    {
        $viewer = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
            'admin_access' => true,
        ]);

        $this->actingAs($viewer)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Настройки агента')
            ->assertDontSee('Сохранить');

        $this->actingAs($viewer)
            ->put('/admin/settings', [
                'max_news_age_hours' => 72,
                'event_similarity_threshold' => 0.72,
            ])
            ->assertForbidden();
    }

    public function test_disabled_collection_does_not_request_source_feeds(): void
    {
        $this->seed();
        Source::query()->firstOrFail()->update([
            'feed_url' => 'https://example.test/feed.xml',
            'is_active' => true,
            'is_allowed' => true,
            'next_poll_at' => null,
        ]);
        app(AgentSettings::class)->update([
            'collection_enabled' => false,
            'automatic_publication' => true,
            'max_news_age_hours' => 72,
            'event_similarity_threshold' => 0.72,
        ]);

        $fetcher = Mockery::mock(HttpFetcher::class);
        $fetcher->shouldNotReceive('get');
        $this->app->instance(HttpFetcher::class, $fetcher);

        self::assertSame(
            ['sources' => 0, 'discovered' => 0, 'failed' => 0],
            app(SourceMonitor::class)->monitor(force: true),
        );
    }
}
