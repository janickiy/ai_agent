<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\NewsMonitor\Models\ProcessingLog;
use App\NewsMonitor\Models\PublicationPost;
use App\NewsMonitor\Models\Source;
use App\NewsMonitor\Models\SourceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdminDataTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_admin_lists_support_server_side_search(): void
    {
        $this->seed();
        $administrator = $this->administrator('current@example.test');
        $secondAdministrator = $this->administrator('unique-admin@example.test');
        $source = Source::query()->firstOrFail();
        $source->update(['is_active' => false]);
        $category = $source->categories()->firstOrFail();
        $item = SourceItem::query()->create([
            'source_id' => $source->id,
            'discovery_url' => 'https://example.test/unique-material',
            'canonical_url' => 'https://example.test/unique-material',
            'canonical_url_hash' => hash('sha256', 'https://example.test/unique-material'),
            'title_original' => 'Уникальный материал DataTables',
            'status' => 'accepted',
            'discovered_at' => now()->utc(),
            'source_published_at' => now()->utc(),
        ]);
        $post = PublicationPost::query()->create([
            'source_item_id' => $item->id,
            'idempotency_key' => 'datatable-post',
            'source_url' => $item->canonical_url,
            'source_name' => $source->name,
            'source_published_at' => now()->utc(),
            'title_original' => 'Уникальный готовый пост',
            'description_original' => 'Описание готового поста.',
            'read_more_label' => 'Читать в источнике',
            'category_id' => $category->id,
            'hashtags' => ['#DataTables'],
            'content_hash' => hash('sha256', 'datatable-post'),
            'status' => 'ready',
            'validation_meta' => [],
            'ready_at' => now()->utc(),
        ]);
        ProcessingLog::query()->create([
            'correlation_id' => (string) Str::uuid(),
            'source_id' => $source->id,
            'source_item_id' => $item->id,
            'publication_post_id' => $post->id,
            'stage' => 'publish',
            'status' => 'error',
            'attempt' => 1,
            'error_message' => 'Уникальная ошибка DataTables',
            'started_at' => now()->utc(),
        ]);

        $cases = [
            'categories' => [
                'route' => 'admin.datatables.categories',
                'column' => ['data' => 'name', 'name' => 'name'],
                'search' => $category->name,
                'path' => 'data.0.name',
                'expected' => $category->name,
            ],
            'sources' => [
                'route' => 'admin.datatables.sources',
                'column' => ['data' => 'domain', 'name' => 'domain'],
                'search' => $source->domain,
                'path' => 'data.0.domain',
                'expected' => $source->domain,
            ],
            'items' => [
                'route' => 'admin.datatables.items',
                'column' => ['data' => 'title_original', 'name' => 'title_original'],
                'search' => 'Уникальный материал DataTables',
                'path' => 'data.0.title_original',
                'expected' => 'Уникальный материал DataTables',
            ],
            'posts' => [
                'route' => 'admin.datatables.posts',
                'column' => ['data' => 'title_original', 'name' => 'title_original'],
                'search' => 'Уникальный готовый пост',
                'path' => 'data.0.title_original',
                'expected' => 'Уникальный готовый пост',
            ],
            'logs' => [
                'route' => 'admin.datatables.logs',
                'column' => ['data' => 'error_message', 'name' => 'error_message'],
                'search' => 'Уникальная ошибка DataTables',
                'path' => 'data.0.error_message',
                'expected' => 'Уникальная ошибка DataTables',
            ],
            'administrators' => [
                'route' => 'admin.datatables.administrators',
                'column' => ['data' => 'email', 'name' => 'email'],
                'search' => $secondAdministrator->email,
                'path' => 'data.0.email',
                'expected' => $secondAdministrator->email,
            ],
        ];

        foreach ($cases as $name => $case) {
            $response = $this->actingAs($administrator)
                ->getJson(route($case['route'], $this->dataTableQuery([$case['column']], $case['search'])))
                ->assertOk();

            self::assertSame(
                1,
                $response->json('recordsFiltered'),
                $name,
            );
            $response
                ->assertJsonCount(1, 'data')
                ->assertJsonPath($case['path'], $case['expected']);

            if ($name === 'sources') {
                $response->assertJsonPath('data.0.DT_RowClass', 'table-danger');
            }

            if ($name === 'items') {
                $response->assertJsonPath('data.0.status_class', 'success');
            }
        }
    }

    public function test_all_item_statuses_have_color_classes(): void
    {
        $this->seed();
        $administrator = $this->administrator('statuses@example.test');
        $source = Source::query()->firstOrFail();
        $expected = [
            'discovered' => 'secondary',
            'fetched' => 'info',
            'extracted' => 'primary',
            'analyzed' => 'warning',
            'rejected_irrelevant' => 'secondary',
            'rejected_advertising' => 'danger',
            'duplicate' => 'dark',
            'validation_failed' => 'danger',
            'accepted' => 'success',
        ];

        foreach ($expected as $status => $class) {
            $url = "https://example.test/status/{$status}";
            SourceItem::query()->create([
                'source_id' => $source->id,
                'discovery_url' => $url,
                'canonical_url' => $url,
                'canonical_url_hash' => hash('sha256', $url),
                'title_original' => "Материал {$status}",
                'status' => $status,
                'discovered_at' => now()->utc(),
            ]);
        }

        $response = $this->actingAs($administrator)
            ->getJson(route('admin.datatables.items', $this->dataTableQuery(
                [['data' => 'status', 'name' => SourceItem::query()->getModel()->getTable().'.status']],
                length: 100,
            )))
            ->assertOk()
            ->assertJsonCount(count($expected), 'data');

        self::assertSame(
            collect($expected)->sortKeys()->all(),
            collect($response->json('data'))
                ->mapWithKeys(static fn (array $row): array => [$row['status'] => $row['status_class']])
                ->sortKeys()
                ->all(),
        );
    }

    public function test_datatable_pagination_and_sorting_are_applied_on_the_server(): void
    {
        $this->administrator('middle@example.test');
        $administrator = $this->administrator('alpha@example.test');
        $this->administrator('zeta@example.test');

        $response = $this->actingAs($administrator)
            ->getJson(route('admin.datatables.administrators', $this->dataTableQuery(
                [['data' => 'email', 'name' => 'email']],
                orderDirection: 'desc',
                length: 2,
            )))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 3)
            ->assertJsonCount(2, 'data');

        self::assertSame(
            ['zeta@example.test', 'middle@example.test'],
            array_column($response->json('data'), 'email'),
        );
    }

    /**
     * @param  list<array{data: string, name: string}>  $columns
     * @return array<string, mixed>
     */
    private function dataTableQuery(
        array $columns,
        string $search = '',
        string $orderDirection = 'asc',
        int $length = 10,
    ): array {
        return [
            'draw' => 1,
            'start' => 0,
            'length' => $length,
            'search' => ['value' => $search, 'regex' => 'false'],
            'columns' => array_map(
                static fn (array $column): array => [
                    ...$column,
                    'searchable' => 'true',
                    'orderable' => 'true',
                    'search' => ['value' => '', 'regex' => 'false'],
                ],
                $columns,
            ),
            'order' => [['column' => 0, 'dir' => $orderDirection]],
        ];
    }

    private function administrator(string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'role' => 'administrator',
            'is_active' => true,
            'admin_access' => true,
        ]);
    }
}
