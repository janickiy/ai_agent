<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\RunNewsMonitor;
use App\Models\User;
use App\NewsMonitor\Models\AuditLog;
use App\NewsMonitor\Services\AgentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class ParserControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_start_parser_and_queue_forced_monitoring(): void
    {
        Queue::fake();
        $administrator = $this->administrator();
        $this->setCollectionEnabled(false);

        $this->actingAs($administrator)
            ->from('/admin/categories')
            ->post('/admin/parser/start')
            ->assertRedirect('/admin/categories')
            ->assertSessionHas(
                'status',
                'Парсер запущен: сбор включён, внеплановая проверка источников добавлена в очередь.',
            );

        self::assertTrue(app(AgentSettings::class)->collectionEnabled());
        Queue::assertPushed(RunNewsMonitor::class, 1);
        self::assertSame(1, AuditLog::query()->where('action', 'parser.started')->count());
    }

    public function test_administrator_can_stop_parser(): void
    {
        $administrator = $this->administrator();
        $this->setCollectionEnabled(true);

        $this->actingAs($administrator)
            ->from('/admin')
            ->post('/admin/parser/stop')
            ->assertRedirect('/admin')
            ->assertSessionHas('status', 'Парсер остановлен: новые циклы сбора отключены.');

        self::assertFalse(app(AgentSettings::class)->collectionEnabled());
        self::assertSame(1, AuditLog::query()->where('action', 'parser.stopped')->count());
    }

    public function test_viewer_cannot_control_parser_or_see_control_buttons(): void
    {
        $viewer = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
            'admin_access' => true,
        ]);

        $this->actingAs($viewer)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('Запустить парсер')
            ->assertDontSee('Остановить парсер');

        $this->actingAs($viewer)->post('/admin/parser/start')->assertForbidden();
        $this->actingAs($viewer)->post('/admin/parser/stop')->assertForbidden();
    }

    private function administrator(): User
    {
        return User::factory()->create([
            'role' => 'administrator',
            'is_active' => true,
            'admin_access' => true,
        ]);
    }

    private function setCollectionEnabled(bool $enabled): void
    {
        app(AgentSettings::class)->update([
            'collection_enabled' => $enabled,
            'automatic_publication' => true,
            'max_news_age_hours' => 72,
            'event_similarity_threshold' => 0.72,
        ]);
    }
}
