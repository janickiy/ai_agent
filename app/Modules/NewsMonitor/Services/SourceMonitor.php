<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Services;

use App\DTO\Catalog\SourceStatusData;
use App\DTO\Pipeline\ProcessingLogData;
use App\DTO\Pipeline\SourceItemData;
use App\Jobs\ProcessSourceItem;
use App\Modules\NewsMonitor\Contracts\HttpFetcher;
use App\Modules\NewsMonitor\Repositories\Catalog\SourceRepository;
use App\Modules\NewsMonitor\Repositories\Pipeline\ProcessingLogRepository;
use App\Modules\NewsMonitor\Repositories\Pipeline\SourceItemRepository;
use Illuminate\Support\Str;
use Throwable;

/**
 * Обходит настроенные RSS/Atom-источники и регистрирует новые материалы для обработки.
 *
 * Сервис отвечает за этап discovery: загрузку ленты, канонизацию ссылок, идемпотентное
 * создание исходных материалов, постановку заданий в очередь и обновление состояния источника.
 */
final class SourceMonitor
{
    public function __construct(
        private readonly HttpFetcher $http,
        private readonly RssParser $rss,
        private readonly UrlCanonicalizer $canonicalizer,
        private readonly SourceRepository $sources,
        private readonly SourceItemRepository $sourceItems,
        private readonly ProcessingLogRepository $processingLogs,
    ) {}


    /**
     * Проверяет один или все доступные источники и возвращает статистику обхода.
     *
     * Новые ссылки сохраняются по уникальному каноническому хешу и ставятся в очередь анализа;
     * успешный обход обновляет расписание, а ошибка отмечает источник и записывается в журнал.
     * Флаг `force` позволяет игнорировать плановое время следующего опроса при ручном запуске.
     *
     * @param int|null $sourceId
     * @param bool $force
     * @return int[]
     * @throws Throwable
     */
    public function monitor(?int $sourceId = null, bool $force = false): array
    {
        $stats = ['sources' => 0, 'discovered' => 0, 'failed' => 0];
        foreach ($this->sources->monitorable($sourceId, $force) as $source) {
            $stats['sources']++;
            try {
                $result = $this->http->get((string) $source->feed_url);
                foreach (array_slice($this->rss->parse($result->body), 0, $source->request_limit) as $discovered) {
                    $url = $this->canonicalizer->canonicalize($discovered->url);
                    $item = $this->sourceItems->firstOrCreateByCanonicalHash(SourceItemData::fromArray([
                        'source_id' => (int) $source->getKey(),
                        'discovery_url' => $discovered->url,
                        'canonical_url' => $url,
                        'canonical_url_hash' => hash('sha256', $url),
                        'status' => 'discovered',
                        'extraction_meta' => ['feed' => $discovered->meta],
                        'discovered_at' => now()->utc(),
                    ]));
                    if ($item->wasRecentlyCreated) {
                        $stats['discovered']++;
                        ProcessSourceItem::dispatch((int) $item->getKey());
                    }
                }
                $this->sources->update($source, SourceStatusData::fromArray([
                    'status' => 'healthy',
                    'last_error' => null,
                    'last_success_at' => now()->utc(),
                    'next_poll_at' => now()->utc()->addMinutes($source->poll_interval_minutes),
                ]));
            } catch (Throwable $exception) {
                $stats['failed']++;
                $this->sources->update($source, SourceStatusData::fromArray([
                    'status' => 'error',
                    'last_error' => Str::limit($exception->getMessage(), 1000),
                    'next_poll_at' => now()->utc()->addMinutes(max(5, $source->poll_interval_minutes)),
                ]));
                $this->processingLogs->record(new ProcessingLogData(
                    correlationId: (string) Str::uuid(),
                    sourceId: (int) $source->getKey(),
                    sourceItemId: null,
                    publicationPostId: null,
                    stage: 'discovery',
                    status: 'error',
                    attempt: 1,
                    durationMs: null,
                    reasonCode: 'source_unavailable',
                    errorMessage: Str::limit($exception->getMessage(), 1000),
                    context: [],
                    startedAt: now()->utc(),
                    finishedAt: now()->utc(),
                ));
            }
        }

        return $stats;
    }
}
