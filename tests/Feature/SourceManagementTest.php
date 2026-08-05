<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\NewsMonitor\Models\Source;
use App\Modules\NewsMonitor\Models\SourceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SourceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_and_toggle_a_source(): void
    {
        $this->seed();
        $administrator = User::factory()->create([
            'role' => 'administrator',
            'is_active' => true,
            'admin_access' => true,
        ]);

        $this->actingAs($administrator)
            ->post(route('admin.sources.store'), [
                'name' => 'Тестовый источник',
                'domain' => 'source-create.example.test',
                'type' => 'rss',
                'source_class' => 'industry_media',
                'trust_score' => 80,
                'base_url' => 'https://source-create.example.test',
                'feed_url' => 'https://source-create.example.test/feed.xml',
                'is_active' => true,
                'is_allowed' => true,
                'is_trusted' => false,
                'poll_interval_minutes' => 30,
                'request_limit' => 20,
                'timeout_seconds' => 10,
                'max_attempts' => 3,
                'category_ids' => [],
            ])
            ->assertRedirect(route('admin.sources.index'))
            ->assertSessionHas('status', 'Источник добавлен.');

        $source = Source::query()->where('domain', 'source-create.example.test')->firstOrFail();
        self::assertTrue($source->is_active);

        $this->actingAs($administrator)
            ->from(route('admin.sources.index'))
            ->patch(route('admin.sources.toggle', $source))
            ->assertRedirect(route('admin.sources.index'))
            ->assertSessionHas('status', 'Состояние источника изменено.');

        self::assertFalse($source->fresh()->is_active);
    }

    public function test_administrator_can_edit_and_delete_an_unused_source(): void
    {
        $this->seed();
        $administrator = User::factory()->create([
            'role' => 'administrator',
            'is_active' => true,
            'admin_access' => true,
        ]);
        $source = Source::query()->where('domain', 'erzrf.ru')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('admin.sources.index'))
            ->assertOk()
            ->assertSee(route('admin.sources.create'))
            ->assertDontSee('Период поиска, минут');

        $this->actingAs($administrator)
            ->get(route('admin.sources.create'))
            ->assertOk()
            ->assertSee('Добавление источника')
            ->assertSee('Период поиска, минут')
            ->assertDontSee('Формат ленты');

        $this->actingAs($administrator)
            ->get(route('admin.sources.edit', $source))
            ->assertOk()
            ->assertSee('Редактирование источника')
            ->assertSee('Период поиска, минут');

        $this->actingAs($administrator)
            ->put(route('admin.sources.update', $source), [
                'name' => 'ЕРЗ России',
                'domain' => 'erzrf.ru',
                'type' => 'rss',
                'source_class' => 'industry_media',
                'trust_score' => 75,
                'base_url' => 'https://erzrf.ru',
                'feed_url' => null,
                'is_active' => true,
                'is_allowed' => true,
                'is_trusted' => false,
                'poll_interval_minutes' => 45,
                'request_limit' => 30,
                'timeout_seconds' => 20,
                'max_attempts' => 3,
                'category_ids' => [],
            ])
            ->assertRedirect();

        $source->refresh();
        self::assertSame('ЕРЗ России', $source->name);
        self::assertSame(45, $source->poll_interval_minutes);

        $this->actingAs($administrator)
            ->delete(route('admin.sources.destroy', $source))
            ->assertRedirect(route('admin.sources.index'));

        $this->assertDatabaseMissing($source->getTable(), ['id' => $source->id]);
    }

    public function test_source_with_materials_cannot_be_deleted(): void
    {
        $this->seed();
        $administrator = User::factory()->create([
            'role' => 'administrator',
            'is_active' => true,
            'admin_access' => true,
        ]);
        $source = Source::query()->firstOrFail();
        SourceItem::query()->create([
            'source_id' => $source->id,
            'discovery_url' => 'https://example.test/news/1',
            'canonical_url' => 'https://example.test/news/1',
            'canonical_url_hash' => hash('sha256', 'https://example.test/news/1'),
            'status' => 'discovered',
            'discovered_at' => now()->utc(),
        ]);

        $this->actingAs($administrator)
            ->from(route('admin.sources.index'))
            ->delete(route('admin.sources.destroy', $source))
            ->assertRedirect(route('admin.sources.index'))
            ->assertSessionHasErrors('source');

        $this->assertDatabaseHas($source->getTable(), ['id' => $source->id]);
    }
}
