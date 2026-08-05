<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessSourceItem;
use App\Models\User;
use App\Modules\NewsMonitor\Models\Source;
use App\Modules\NewsMonitor\Models\SourceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class NewsItemManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_queue_an_item_for_reprocessing(): void
    {
        $this->seed();
        $administrator = User::factory()->create([
            'role' => 'administrator',
            'is_active' => true,
            'admin_access' => true,
        ]);
        $source = Source::query()->firstOrFail();
        $item = SourceItem::query()->create([
            'source_id' => $source->id,
            'discovery_url' => 'https://retry.example.test/news/1',
            'canonical_url' => 'https://retry.example.test/news/1',
            'canonical_url_hash' => hash('sha256', 'https://retry.example.test/news/1'),
            'status' => 'validation_failed',
            'discovered_at' => now()->utc(),
        ]);
        Queue::fake([ProcessSourceItem::class]);

        $this->actingAs($administrator)
            ->from(route('admin.items.index'))
            ->post(route('admin.items.retry', $item))
            ->assertRedirect(route('admin.items.index'))
            ->assertSessionHas('status', 'Повторная обработка поставлена в очередь.');

        Queue::assertPushed(
            ProcessSourceItem::class,
            static fn (ProcessSourceItem $job): bool => $job->sourceItemId === $item->id,
        );
    }
}
