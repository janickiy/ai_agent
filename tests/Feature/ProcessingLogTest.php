<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\NewsMonitor\Models\ProcessingLog;
use App\NewsMonitor\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ProcessingLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_can_view_and_filter_processing_logs(): void
    {
        $this->seed();
        $user = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
            'admin_access' => true,
        ]);
        $source = Source::query()->firstOrFail();
        $this->log($source, 'fetch', 'error', 'fetch_timeout', 'Connection timeout after 20 seconds');
        $this->log($source, 'analyze', 'success', 'analysis_complete');

        $this->actingAs($user)
            ->get(route('admin.logs.index'))
            ->assertOk()
            ->assertSee('Журнал и ошибки');

        $this->actingAs($user)
            ->getJson(route('admin.datatables.logs'))
            ->assertOk()
            ->assertSee('Connection timeout after 20 seconds')
            ->assertSee('analysis_complete');

        $this->actingAs($user)
            ->getJson(route('admin.datatables.logs', [
                'stage' => 'fetch',
                'status' => 'error',
            ]))
            ->assertOk()
            ->assertSee('Connection timeout after 20 seconds')
            ->assertDontSee('analysis_complete');
    }

    private function log(
        Source $source,
        string $stage,
        string $status,
        ?string $reason = null,
        ?string $error = null,
    ): void {
        ProcessingLog::query()->create([
            'correlation_id' => (string) Str::uuid(),
            'source_id' => $source->id,
            'stage' => $stage,
            'status' => $status,
            'attempt' => 1,
            'duration_ms' => 1250,
            'reason_code' => $reason,
            'error_message' => $error,
            'context' => ['ai_provider' => 'rules'],
            'started_at' => now()->utc(),
            'finished_at' => now()->utc(),
        ]);
    }
}
