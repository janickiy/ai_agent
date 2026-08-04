<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Services;

use App\DTO\Pipeline\ItemAnalysisData;
use App\DTO\Pipeline\ItemDuplicateData;
use App\DTO\Pipeline\ProcessingLogData;
use App\DTO\Pipeline\PublicationPostData;
use App\DTO\Pipeline\SourceItemData;
use App\Modules\NewsMonitor\AI\Contracts\AIProvider;
use App\Modules\NewsMonitor\AI\DTO\ArticleAnalysisRequest;
use App\Modules\NewsMonitor\AI\DTO\ArticleComparisonRequest;
use App\Modules\NewsMonitor\Contracts\HttpFetcher;
use App\Modules\NewsMonitor\Models\NewsCategory;
use App\Modules\NewsMonitor\Models\PublicationPost;
use App\Modules\NewsMonitor\Models\SourceItem;
use App\Modules\NewsMonitor\Repositories\Catalog\NewsCategoryRepository;
use App\Modules\NewsMonitor\Repositories\Pipeline\ItemAnalysisRepository;
use App\Modules\NewsMonitor\Repositories\Pipeline\ItemDuplicateRepository;
use App\Modules\NewsMonitor\Repositories\Pipeline\ProcessingLogRepository;
use App\Modules\NewsMonitor\Repositories\Pipeline\PublicationPostRepository;
use App\Modules\NewsMonitor\Repositories\Pipeline\SourceItemRepository;
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
        private readonly SourceItemRepository $sourceItems,
        private readonly NewsCategoryRepository $categories,
        private readonly ItemAnalysisRepository $itemAnalyses,
        private readonly ItemDuplicateRepository $itemDuplicates,
        private readonly PublicationPostRepository $publicationPosts,
        private readonly ProcessingLogRepository $processingLogs,
    ) {}

    public function process(SourceItem $item): ?PublicationPost
    {
        if ($existing = $this->publicationPosts->findBySourceItemId((int) $item->getKey())) {
            return $existing;
        }

        $item = $this->sourceItems->withSource($item);

        $correlationId = (string) Str::uuid();
        $pipelineStarted = hrtime(true);

        try {
            $fetchStarted = hrtime(true);
            $fetched = $this->http->get($item->canonical_url);
            $item = $this->sourceItems->update($item, SourceItemData::fromArray([
                'status' => 'fetched',
                'fetched_at' => now()->utc(),
            ]));
            $this->log($item, $correlationId, 'fetch', 'success', $fetchStarted);

            $extractStarted = hrtime(true);
            $fallback = is_array($item->extraction_meta) ? ($item->extraction_meta['feed'] ?? []) : [];
            $article = $this->extractor->extract($fetched->body, $fetched->url, is_array($fallback) ? $fallback : []);
            $contentForHash = $article->body !== '' ? $article->body : "{$article->title}\n{$article->description}";
            $titleDescriptionHash = hash('sha256', "{$article->title}\n{$article->description}");
            $contentHash = hash('sha256', $contentForHash);
            $canonicalHash = hash('sha256', $article->canonicalUrl);

            $canonicalDuplicate = $this->sourceItems->findOtherByCanonicalUrlHash(
                $item->getKey(),
                $canonicalHash,
            );
            if ($canonicalDuplicate) {
                $item = $this->sourceItems->update($item, SourceItemData::fromArray([
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
                ]));
                $this->itemDuplicates->upsertForSourceItem(new ItemDuplicateData(
                    sourceItemId: (int) $item->getKey(),
                    originalSourceItemId: (int) $canonicalDuplicate->getKey(),
                    method: 'canonical_url',
                    similarity: 1.0,
                    algorithmVersion: 'url-canonicalizer-v1',
                    meta: ['canonical_url' => $article->canonicalUrl],
                ));
                $this->log($item, $correlationId, 'deduplicate', 'rejected', $extractStarted, 'canonical_url');

                return null;
            }

            $item = $this->sourceItems->update($item, SourceItemData::fromArray([
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
            ]));
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
                : $this->categories->findActiveByCode($analysis->categoryCode);
            $isActual = $this->isActual($article->publishedAt);
            $hashtags = $this->hashtags($analysis->hashtags, $category);

            $this->itemAnalyses->upsertForSourceItem(new ItemAnalysisData(
                sourceItemId: (int) $item->getKey(),
                categoryId: $category === null ? null : (int) $category->getKey(),
                isActual: $isActual,
                actualityScore: $isActual ? 1.0 : 0.0,
                isAdvertising: $analysis->isAdvertising,
                adConfidence: $analysis->adConfidence,
                categoryConfidence: $analysis->categoryConfidence,
                hashtags: $hashtags,
                entities: $analysis->entities,
                provider: $analysis->provider,
                model: $analysis->model,
                promptVersion: (string) config('ai.prompt_version'),
                decisionMeta: ['reason' => $analysis->reason],
                checkedAt: now()->utc(),
            ));
            $item = $this->sourceItems->update($item, SourceItemData::fromArray([
                'status' => 'analyzed',
                'analyzed_at' => now()->utc(),
            ]));
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
                $this->itemDuplicates->upsertForSourceItem(new ItemDuplicateData(
                    sourceItemId: (int) $item->getKey(),
                    originalSourceItemId: (int) $duplicate['item']->getKey(),
                    method: $duplicate['method'],
                    similarity: $duplicate['similarity'],
                    algorithmVersion: $duplicate['algorithm'],
                    meta: [],
                ));
                $this->log($item, $correlationId, 'deduplicate', 'rejected', $duplicateStarted, $duplicate['method']);

                return $this->reject($item, $correlationId, 'duplicate', $duplicate['method']);
            }
            $this->log($item, $correlationId, 'deduplicate', 'success', $duplicateStarted);

            if (! $this->settings->automaticPublication()) {
                $item = $this->sourceItems->update($item, SourceItemData::fromArray([
                    'status' => 'analyzed',
                    'rejection_reason' => 'publication_output_disabled',
                ]));
                $this->log($item, $correlationId, 'decision', 'pending', hrtime(true), 'publication_output_disabled');

                return null;
            }

            $item = $this->sourceItems->withSource($item);
            $publishStarted = hrtime(true);
            $post = DB::transaction(function () use ($item, $category, $hashtags, $contentHash, $article): PublicationPost {
                $post = $this->publicationPosts->firstOrCreateForSourceItem(new PublicationPostData(
                    sourceItemId: (int) $item->getKey(),
                    idempotencyKey: hash('sha256', $item->id.':'.$article->canonicalUrl.':'.$contentHash),
                    sourceUrl: $article->canonicalUrl,
                    sourceName: $item->source->name,
                    sourcePublishedAt: $article->publishedAt,
                    titleOriginal: $article->title,
                    descriptionOriginal: $article->description,
                    imageUrl: $item->image_url,
                    imageStorageKey: null,
                    readMoreLabel: 'Читать в источнике',
                    categoryId: (int) $category->getKey(),
                    hashtags: $hashtags,
                    contentHash: $contentHash,
                    status: 'ready',
                    validationMeta: [
                        'title_hash' => hash('sha256', $article->title),
                        'description_hash' => hash('sha256', $article->description),
                        'rules_version' => 'post-validator-v1',
                        'copied_fields_unchanged' => true,
                    ],
                    readyAt: now()->utc(),
                ));
                $this->sourceItems->update($item, SourceItemData::fromArray([
                    'status' => 'accepted',
                    'rejection_reason' => null,
                ]));

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
        $exact = $this->sourceItems->findExactDuplicate(
            $item->getKey(),
            (string) $item->title_description_hash,
            (string) $item->content_hash,
        );
        if ($exact) {
            return [
                'item' => $exact,
                'method' => $exact->content_hash === $item->content_hash ? 'content_hash' : 'title_description_hash',
                'similarity' => 1.0,
                'algorithm' => 'sha256-v1',
            ];
        }

        $candidates = $this->sourceItems->semanticDuplicateCandidates(
            $item->getKey(),
            $categoryId,
            $item->source_published_at->copy()->subDays(2),
            $item->source_published_at->copy()->addDays(2),
        );

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
        $item = $this->sourceItems->update($item, SourceItemData::fromArray([
            'status' => $status,
            'rejection_reason' => $reason,
        ]));
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
        $this->processingLogs->record(new ProcessingLogData(
            correlationId: $correlationId,
            sourceId: (int) $item->source_id,
            sourceItemId: (int) $item->getKey(),
            publicationPostId: $publicationPostId,
            stage: $stage,
            status: $status,
            attempt: 1,
            durationMs: (int) ((hrtime(true) - $started) / 1_000_000),
            reasonCode: $reason,
            errorMessage: $error === null ? null : Str::limit($error, 1000),
            context: ['ai_provider' => $this->ai->code()],
            startedAt: now()->utc(),
            finishedAt: now()->utc(),
        ));
    }
}
