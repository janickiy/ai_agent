<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\NewsMonitor\Models\AuditLog;
use App\NewsMonitor\Models\ProcessingLog;
use App\NewsMonitor\Models\PublicationPost;
use App\NewsMonitor\Models\SourceItem;
use App\NewsMonitor\Support\NewsTables;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

final class DataCleanupController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Gate::authorize('purge-content');

        if (config('queue.default') === 'redis') {
            Queue::clear('analysis');
        }

        $counts = DB::transaction(function () use ($request): array {
            $counts = [
                'source_items' => SourceItem::query()->count(),
                'posts' => PublicationPost::query()->count(),
                'processing_logs' => ProcessingLog::query()->count(),
            ];

            foreach ([
                'processing_logs',
                'posts',
                'event_items',
                'duplicates',
                'analyses',
                'source_items',
                'events',
            ] as $table) {
                DB::table(NewsTables::name($table))->delete();
            }

            AuditLog::query()->create([
                'user_id' => $request->user()->id,
                'correlation_id' => (string) Str::uuid(),
                'action' => 'content.purged',
                'entity_type' => 'monitoring_content',
                'entity_id' => null,
                'before' => $counts,
                'after' => [
                    'source_items' => 0,
                    'posts' => 0,
                    'processing_logs' => 0,
                ],
                'result' => 'success',
                'created_at' => now()->utc(),
            ]);

            return $counts;
        });

        return redirect()->route('admin.dashboard')->with(
            'status',
            sprintf(
                'Данные очищены: исходных публикаций — %d, готовых постов — %d, записей журнала — %d.',
                $counts['source_items'],
                $counts['posts'],
                $counts['processing_logs'],
            ),
        );
    }
}
