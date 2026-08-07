<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Repositories\ContentCleanupRepository;
use App\Modules\NewsMonitor\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;

final class DataCleanupController extends Controller
{
    /**
     * Получает репозиторий очистки мониторинговых данных и сервис аудита операции.
     */
    public function __construct(
        private readonly ContentCleanupRepository $content,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Удаляет накопленные материалы, публикации и журналы обработки по запросу администратора.
     *
     * Операция выполняется транзакционно, записывается в аудит и очищает очередь анализа,
     * когда приложение использует Redis в качестве драйвера очередей.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        Gate::authorize('purge-content');

        $counts = DB::transaction(function () use ($request): array {
            $counts = $this->content->purge();

            $this->audit->record(
                $request->user()->id,
                'content.purged',
                'monitoring_content',
                null,
                $counts,
                [
                    'source_items' => 0,
                    'posts' => 0,
                    'processing_logs' => 0,
                ],
            );

            return $counts;
        });

        if (config('queue.default') === 'redis') {
            Queue::clear('analysis');
        }

        return redirect()->route('admin.dashboard')->with(
            'status',
            sprintf(
                'Данные очищены: исходных публикаций — %d, опубликованных постов — %d, записей журнала — %d.',
                $counts['source_items'],
                $counts['posts'],
                $counts['processing_logs'],
            ),
        );
    }
}
