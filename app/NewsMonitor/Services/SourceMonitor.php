<?php

declare(strict_types=1);

namespace App\NewsMonitor\Services;

use App\Jobs\ProcessSourceItem;
use App\NewsMonitor\Contracts\HttpFetcher;
use App\NewsMonitor\Models\ProcessingLog;
use App\NewsMonitor\Models\Source;
use App\NewsMonitor\Models\SourceItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Throwable;

final class SourceMonitor
{
    public function __construct(
        private readonly HttpFetcher $http,
        private readonly RssParser $rss,
        private readonly UrlCanonicalizer $canonicalizer,
        private readonly AgentSettings $settings,
    ) {}

    /** @return array{sources: int, discovered: int, failed: int} */
    public function monitor(?int $sourceId = null, bool $force = false): array
    {
        if (! $this->settings->collectionEnabled()) {
            return ['sources' => 0, 'discovered' => 0, 'failed' => 0];
        }

        $query = Source::query()
            ->where('is_active', true)
            ->where('is_allowed', true)
            ->whereNotNull('feed_url')
            ->when($sourceId, static fn (Builder $builder) => $builder->whereKey($sourceId))
            ->when(! $force, static fn (Builder $builder) => $builder->where(
                static fn (Builder $due) => $due->whereNull('next_poll_at')->orWhere('next_poll_at', '<=', now()->utc()),
            ));

        $stats = ['sources' => 0, 'discovered' => 0, 'failed' => 0];
        foreach ($query->cursor() as $source) {
            $stats['sources']++;
            try {
                $result = $this->http->get((string) $source->feed_url);
                foreach (array_slice($this->rss->parse($result->body), 0, $source->request_limit) as $discovered) {
                    $url = $this->canonicalizer->canonicalize($discovered->url);
                    $item = SourceItem::query()->firstOrCreate(
                        ['canonical_url_hash' => hash('sha256', $url)],
                        [
                            'source_id' => $source->id,
                            'discovery_url' => $discovered->url,
                            'canonical_url' => $url,
                            'status' => 'discovered',
                            'extraction_meta' => ['feed' => $discovered->meta],
                            'discovered_at' => now()->utc(),
                        ],
                    );
                    if ($item->wasRecentlyCreated) {
                        $stats['discovered']++;
                        ProcessSourceItem::dispatch($item->id);
                    }
                }
                $source->update([
                    'status' => 'healthy',
                    'last_error' => null,
                    'last_success_at' => now()->utc(),
                    'next_poll_at' => now()->utc()->addMinutes($source->poll_interval_minutes),
                ]);
            } catch (Throwable $exception) {
                $stats['failed']++;
                $source->update([
                    'status' => 'error',
                    'last_error' => Str::limit($exception->getMessage(), 1000),
                    'next_poll_at' => now()->utc()->addMinutes(max(5, $source->poll_interval_minutes)),
                ]);
                ProcessingLog::query()->create([
                    'correlation_id' => (string) Str::uuid(),
                    'source_id' => $source->id,
                    'stage' => 'discovery',
                    'status' => 'error',
                    'attempt' => 1,
                    'reason_code' => 'source_unavailable',
                    'error_message' => Str::limit($exception->getMessage(), 1000),
                    'context' => [],
                    'started_at' => now()->utc(),
                    'finished_at' => now()->utc(),
                ]);
            }
        }

        return $stats;
    }
}
