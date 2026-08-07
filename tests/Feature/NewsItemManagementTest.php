<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessSourceItem;
use App\Jobs\PublishKaboomPost;
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

    public function test_administrator_can_queue_one_analyzed_item_for_manual_publication(): void
    {
        $this->seed();
        $administrator = $this->administrator();
        $item = $this->item('manual-one', 'analyzed', SourceItem::MANUAL_PUBLICATION_REASON);
        Queue::fake([PublishKaboomPost::class]);

        $this->actingAs($administrator)
            ->from(route('admin.items.index'))
            ->post(route('admin.items.publish', $item))
            ->assertRedirect(route('admin.items.index'))
            ->assertSessionHas('status', 'Материал поставлен в очередь ручной публикации.');

        Queue::assertPushed(
            PublishKaboomPost::class,
            static fn (PublishKaboomPost $job): bool => $job->sourceItemId === $item->id
                && $job->queue === 'publishing',
        );
        self::assertSame(SourceItem::PUBLICATION_QUEUED_REASON, $item->fresh()->rejection_reason);
    }

    public function test_administrator_can_queue_several_eligible_items_and_ineligible_items_are_skipped(): void
    {
        $this->seed();
        $administrator = $this->administrator();
        $first = $this->item('manual-first', 'analyzed', SourceItem::MANUAL_PUBLICATION_REASON);
        $second = $this->item('manual-second', 'analyzed', SourceItem::MANUAL_PUBLICATION_REASON);
        $ineligible = $this->item('already-accepted', 'accepted');
        Queue::fake([PublishKaboomPost::class]);

        $this->actingAs($administrator)
            ->from(route('admin.items.index'))
            ->post(route('admin.items.publish-many'), [
                'item_ids' => [$first->id, $second->id, $ineligible->id],
            ])
            ->assertRedirect(route('admin.items.index'))
            ->assertSessionHas(
                'status',
                'Материалы поставлены в очередь ручной публикации: 2. Пропущено: 1.',
            );

        Queue::assertPushed(PublishKaboomPost::class, 2);
        Queue::assertPushed(
            PublishKaboomPost::class,
            static fn (PublishKaboomPost $job): bool => in_array(
                $job->sourceItemId,
                [$first->id, $second->id],
                true,
            ),
        );
        Queue::assertNotPushed(
            PublishKaboomPost::class,
            static fn (PublishKaboomPost $job): bool => $job->sourceItemId === $ineligible->id,
        );
    }

    public function test_ineligible_item_cannot_be_manually_published(): void
    {
        $this->seed();
        $administrator = $this->administrator();
        $item = $this->item('advertising', 'rejected_advertising', 'advertising_detected');
        Queue::fake([PublishKaboomPost::class]);

        $this->actingAs($administrator)
            ->from(route('admin.items.index'))
            ->post(route('admin.items.publish', $item))
            ->assertRedirect(route('admin.items.index'))
            ->assertSessionHasErrors('item');

        Queue::assertNothingPushed();
    }

    public function test_user_without_pipeline_permission_cannot_publish_items(): void
    {
        $this->seed();
        $viewer = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
            'admin_access' => true,
        ]);
        $item = $this->item('viewer-forbidden', 'analyzed', SourceItem::MANUAL_PUBLICATION_REASON);
        Queue::fake([PublishKaboomPost::class]);

        $this->actingAs($viewer)
            ->post(route('admin.items.publish', $item))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('admin.items.publish-many'), ['item_ids' => [$item->id]])
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_recovery_command_requeues_stale_kaboom_publication(): void
    {
        $this->seed();
        $item = $this->item(
            'stale-kaboom-publication',
            'accepted',
            SourceItem::PUBLICATION_QUEUED_REASON,
        );
        SourceItem::query()->whereKey($item->getKey())->update([
            'updated_at' => now()->utc()->subMinutes(15),
        ]);
        Queue::fake([PublishKaboomPost::class]);

        $this->artisan('news:recover-publications', ['--minutes' => 10])
            ->expectsOutput('Найдено: 1; повторно поставлено: 1; ошибок: 0.')
            ->assertSuccessful();

        Queue::assertPushed(
            PublishKaboomPost::class,
            static fn (PublishKaboomPost $job): bool => $job->sourceItemId === $item->id,
        );
    }

    private function administrator(): User
    {
        return User::factory()->create([
            'role' => 'administrator',
            'is_active' => true,
            'admin_access' => true,
        ]);
    }

    private function item(string $slug, string $status, ?string $reason = null): SourceItem
    {
        $source = Source::query()->firstOrFail();
        $url = "https://manual.example.test/news/{$slug}";

        return SourceItem::query()->create([
            'source_id' => $source->id,
            'discovery_url' => $url,
            'canonical_url' => $url,
            'canonical_url_hash' => hash('sha256', $url),
            'status' => $status,
            'rejection_reason' => $reason,
            'discovered_at' => now()->utc(),
        ]);
    }
}
