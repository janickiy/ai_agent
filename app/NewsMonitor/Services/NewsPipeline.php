<?php

declare(strict_types=1);

namespace App\NewsMonitor\Services;

use App\NewsMonitor\AI\Contracts\AIProvider;
use App\NewsMonitor\AI\DTO\ArticleAnalysisRequest;
use App\NewsMonitor\AI\DTO\ArticleComparisonRequest;
use App\NewsMonitor\Contracts\HttpFetcher;
use App\NewsMonitor\Models\ItemAnalysis;
use App\NewsMonitor\Models\ItemDuplicate;
use App\NewsMonitor\Models\NewsCategory;
use App\NewsMonitor\Models\ProcessingLog;
use App\NewsMonitor\Models\PublicationPost;
use App\NewsMonitor\Models\SourceItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class NewsPipeline
{
    public function __construct(
        private readonly HttpFetcher $http,
        private readonly ArticleExtractor $extractor,
        private readonly AIProvider $ai,
        private readonly AgentSettings $settings,
    ) {}

    public function process(SourceItem $item): ?PublicationPost
    {
        if ($existing = $item->publicationPost()->first()) {
            return $existing;
        }

        $correlationId = (string) Str::uuid();
        $pipelineStarted = hrtime(true);

        try {
            $fetchStarted = hrtime(true);
            $fetched = $this->http->get($item->canonical_url);
            $item->update(['status' => 'fetched', 'fetched_at' => now()->utc()]);
            $this->log($item, $correlationId, 'fetch', 'success', $fetchStarted);

            $extractStarted = hrtime(true);
            $fallback = is_array($item->extraction_meta) ? ($item->extraction_meta['feed'] ?? []) : [];
            $article = $this->extractor->extract($fetched->body, $fetched->url, is_array($fallback) ? $fallback : []);
            $contentForHash = $article->body !== '' ? $article->body : "{$article->title}\n{$article->description}";
            $titleDescriptionHash = hash('sha256', "{$article->title}\n{$article->description}");
            $contentHash = hash('sha256', $contentForHash);
            $canonicalHash = hash('sha256', $article->canonicalUrl);

            $canonicalDuplicate = SourceItem::query()
                ->whereKeyNot($item->id)
                ->where('canonical_url_hash', $canonicalHash)
                ->first();
            if ($canonicalDuplicate) {
                $item->update([
                    'title_original' => $article->title,
                    'description_original' => $article->description,
                    'body_text' => $article->body,
                    'image_url' => $this->safeImageUrl($article->imageUrl),
                    'source_published_at' => $article->publishedAt,
                    'title_description_hash' => $titleDescriptionHash,
                    'content_hash' => $contentHash,
                    'status' => 'duplicate',
                    'rejection_reason' => 'canonical_url',
                    'extracted_at' => now()->utc(),
                ]);
                ItemDuplicate::query()->updateOrCreate(
                    ['source_item_id' => $item->id],
                    [
                        'original_source_item_id' => $canonicalDuplicate->id,
                        'method' => 'canonical_url',
                        'similarity' => 1,
                        'algorithm_version' => 'url-canonicalizer-v1',
                        'meta' => ['canonical_url' => $article->canonicalUrl],
                    ],
                );
                $this->log($item, $correlationId, 'deduplicate', 'rejected', $extractStarted, 'canonical_url');

                return null;
            }

            $item->update([
                'canonical_url' => $article->canonicalUrl,
                'canonical_url_hash' => $canonicalHash,
                'title_original' => $article->title,
                'description_original' => $article->description,
                'body_text' => $article->body,
                'image_url' => $this->safeImageUrl($article->imageUrl),
                'source_published_at' => $article->publishedAt,
                'title_description_hash' => $titleDescriptionHash,
                'content_hash' => $contentHash,
                'status' => 'extracted',
                'extraction_meta' => [
                    ...(is_array($item->extraction_meta) ? $item->extraction_meta : []),
                    'extractor' => $article->meta,
                    'response_headers' => $this->safeHeaders($fetched->headers),
                ],
                'extracted_at' => now()->utc(),
            ]);
            $this->log($item, $correlationId, 'extract', 'success', $extractStarted);

            if ($article->title === '' || $article->description === '' || $article->publishedAt === null) {
                return $this->reject(
                    $item,
                    $correlationId,
                    'validation_failed',
                    $article->publishedAt === null ? 'publication_date_missing' : 'required_copied_field_missing',
                );
            }

            $analysisStarted = hrtime(true);
            $analysis = $this->ai->analyzeArticle(new ArticleAnalysisRequest(
                $article->title,
                $article->description,
                $article->body,
                config('news.categories'),
            ));
            $category = $analysis->categoryCode === null
                ? null
                : NewsCategory::query()->where('code', $analysis->categoryCode)->where('is_active', true)->first();
            $isActual = $this->isActual($article->publishedAt);
            $hashtags = $this->hashtags($analysis->hashtags, $category);

            ItemAnalysis::query()->updateOrCreate(
                ['source_item_id' => $item->id],
                [
                    'category_id' => $category?->id,
                    'is_actual' => $isActual,
                    'actuality_score' => $isActual ? 1 : 0,
                    'is_advertising' => $analysis->isAdvertising,
                    'ad_confidence' => $analysis->adConfidence,
                    'category_confidence' => $analysis->categoryConfidence,
                    'hashtags' => $hashtags,
                    'entities' => $analysis->entities,
                    'provider' => $analysis->provider,
                    'model' => $analysis->model,
                    'prompt_version' => (string) config('ai.prompt_version'),
                    'decision_meta' => ['reason' => $analysis->reason],
                    'checked_at' => now()->utc(),
                ],
            );
            $item->update(['status' => 'analyzed', 'analyzed_at' => now()->utc()]);
            $this->log($item, $correlationId, 'analyze', 'success', $analysisStarted, 'analysis_complete');

            if ($analysis->isAdvertising && $analysis->adConfidence >= (float) config('news.ad_confidence_threshold')) {
                return $this->reject($item, $correlationId, 'rejected_advertising', 'advertising_detected');
            }
            if (! $isActual) {
                return $this->reject($item, $correlationId, 'rejected_irrelevant', 'outside_actuality_window');
            }
            if ($category === null || $analysis->categoryConfidence < (float) config('news.category_confidence_threshold')) {
                return $this->reject($item, $correlationId, 'rejected_irrelevant', 'category_confidence_too_low');
            }

            $duplicateStarted = hrtime(true);
            if ($duplicate = $this->findDuplicate($item, $category->id)) {
                ItemDuplicate::query()->updateOrCreate(
                    ['source_item_id' => $item->id],
                    [
                        'original_source_item_id' => $duplicate['item']->id,
                        'method' => $duplicate['method'],
                        'similarity' => $duplicate['similarity'],
                        'algorithm_version' => $duplicate['algorithm'],
                        'meta' => [],
                    ],
                );
                $this->log($item, $correlationId, 'deduplicate', 'rejected', $duplicateStarted, $duplicate['method']);

                return $this->reject($item, $correlationId, 'duplicate', $duplicate['method']);
            }
            $this->log($item, $correlationId, 'deduplicate', 'success', $duplicateStarted);

            if (! $this->settings->automaticPublication()) {
                $item->update(['status' => 'analyzed', 'rejection_reason' => 'publication_output_disabled']);
                $this->log($item, $correlationId, 'decision', 'pending', hrtime(true), 'publication_output_disabled');

                return null;
            }

            $publishStarted = hrtime(true);
            $post = DB::transaction(function () use ($item, $category, $hashtags, $contentHash, $article): PublicationPost {
                $post = PublicationPost::query()->firstOrCreate(
                    ['source_item_id' => $item->id],
                    [
                        'idempotency_key' => hash('sha256', $item->id.':'.$article->canonicalUrl.':'.$contentHash),
                        'source_url' => $article->canonicalUrl,
                        'source_name' => $item->source->name,
                        'source_published_at' => $article->publishedAt,
                        'title_original' => $article->title,
                        'description_original' => $article->description,
                        'image_url' => $item->image_url,
                        'image_storage_key' => null,
                        'read_more_label' => 'Читать в источнике',
                        'category_id' => $category->id,
                        'hashtags' => $hashtags,
                        'content_hash' => $contentHash,
                        'status' => 'ready',
                        'validation_meta' => [
                            'title_hash' => hash('sha256', $article->title),
                            'description_hash' => hash('sha256', $article->description),
                            'rules_version' => 'post-validator-v1',
                            'copied_fields_unchanged' => true,
                        ],
                        'ready_at' => now()->utc(),
                    ],
                );
                $item->update(['status' => 'accepted', 'rejection_reason' => null]);

                return $post;
            }, attempts: 3);
            $this->log($item, $correlationId, 'publish', 'success', $publishStarted, publicationPostId: $post->id);
            $this->log($item, $correlationId, 'pipeline', 'success', $pipelineStarted, publicationPostId: $post->id);

            return $post;
        } catch (Throwable $exception) {
            $this->log(
                $item,
                $correlationId,
                'pipeline',
                'error',
                $pipelineStarted,
                'unhandled_error',
                error: $exception->getMessage(),
            );
            throw $exception;
        }
    }

    /**
     * @return array{item: SourceItem, method: string, similarity: float, algorithm: string}|null
     */
    private function findDuplicate(SourceItem $item, int $categoryId): ?array
    {
        $exact = SourceItem::query()
            ->whereKeyNot($item->id)
            ->where(function ($query) use ($item): void {
                $query->where('title_description_hash', $item->title_description_hash)
                    ->orWhere('content_hash', $item->content_hash);
            })
            ->whereIn('status', ['accepted', 'duplicate'])
            ->orderBy('id')
            ->first();
        if ($exact) {
            return [
                'item' => $exact,
                'method' => $exact->content_hash === $item->content_hash ? 'content_hash' : 'title_description_hash',
                'similarity' => 1.0,
                'algorithm' => 'sha256-v1',
            ];
        }

        $candidates = SourceItem::query()
            ->whereKeyNot($item->id)
            ->where('status', 'accepted')
            ->whereHas('analysis', static fn ($query) => $query->where('category_id', $categoryId))
            ->whereBetween('source_published_at', [
                $item->source_published_at->copy()->subDays(2),
                $item->source_published_at->copy()->addDays(2),
            ])
            ->latest('source_published_at')
            ->limit(20)
            ->get();

        foreach ($candidates as $candidate) {
            $comparison = $this->ai->compareArticles(new ArticleComparisonRequest(
                (string) $item->body_text,
                (string) $candidate->body_text,
            ));
            if ($comparison->similarity >= $this->settings->eventSimilarityThreshold()) {
                return [
                    'item' => $candidate,
                    'method' => 'semantic_similarity',
                    'similarity' => $comparison->similarity,
                    'algorithm' => $comparison->provider.':'.$comparison->model,
                ];
            }
        }

        return null;
    }

    private function reject(SourceItem $item, string $correlationId, string $status, string $reason): null
    {
        $item->update(['status' => $status, 'rejection_reason' => $reason]);
        $this->log($item, $correlationId, 'decision', 'rejected', hrtime(true), $reason);

        return null;
    }

    private function isActual(CarbonInterface $date): bool
    {
        return $date->greaterThanOrEqualTo(now()->utc()->subHours($this->settings->maxNewsAgeHours()))
            && $date->lessThanOrEqualTo(now()->utc()->addHour());
    }

    /** @param list<string> $hashtags @return list<string> */
    private function hashtags(array $hashtags, ?NewsCategory $category): array
    {
        $values = array_filter(array_map(static function (string $hashtag): string {
            $normalized = preg_replace('/\s+/u', '', trim($hashtag)) ?? '';

            return $normalized === '' ? '' : (str_starts_with($normalized, '#') ? $normalized : '#'.$normalized);
        }, $hashtags));
        if ($category !== null) {
            array_unshift($values, $category->hashtag);
        }

        return array_slice(array_values(array_unique($values)), 0, 7);
    }

    private function safeImageUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        try {
            $this->http->assertPublicUrl($url);

            return $url;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, string> $headers @return array<string, string> */
    private function safeHeaders(array $headers): array
    {
        return array_intersect_key($headers, array_flip(['Content-Type', 'Last-Modified', 'ETag']));
    }

    private function log(
        SourceItem $item,
        string $correlationId,
        string $stage,
        string $status,
        int $started,
        ?string $reason = null,
        ?int $publicationPostId = null,
        ?string $error = null,
    ): void {
        ProcessingLog::query()->create([
            'correlation_id' => $correlationId,
            'source_id' => $item->source_id,
            'source_item_id' => $item->id,
            'publication_post_id' => $publicationPostId,
            'stage' => $stage,
            'status' => $status,
            'attempt' => 1,
            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            'reason_code' => $reason,
            'error_message' => $error === null ? null : Str::limit($error, 1000),
            'context' => ['ai_provider' => config('ai.default')],
            'started_at' => now()->utc(),
            'finished_at' => now()->utc(),
        ]);
    }
}
