<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Services;

use App\DTO\Pipeline\ItemAnalysisData;
use App\DTO\Pipeline\ItemDuplicateData;
use App\DTO\Pipeline\ProcessingLogData;
use App\DTO\Pipeline\SourceItemData;
use App\Modules\NewsMonitor\AI\Contracts\AIProvider;
use App\Modules\NewsMonitor\AI\DTO\ArticleAnalysisRequest;
use App\Modules\NewsMonitor\AI\DTO\ArticleComparisonRequest;
use App\Modules\NewsMonitor\Contracts\HttpFetcher;
use App\Modules\NewsMonitor\Models\PublicationPost;
use App\Modules\NewsMonitor\Models\SourceItem;
use App\Modules\NewsMonitor\Repositories\Catalog\NewsCategoryRepository;
use App\Modules\NewsMonitor\Repositories\Pipeline\ItemAnalysisRepository;
use App\Modules\NewsMonitor\Repositories\Pipeline\ItemDuplicateRepository;
use App\Modules\NewsMonitor\Repositories\Pipeline\ProcessingLogRepository;
use App\Modules\NewsMonitor\Repositories\Pipeline\PublicationPostRepository;
use App\Modules\NewsMonitor\Repositories\Pipeline\SourceItemRepository;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Throwable;

/**
 * Координирует полный конвейер обработки одного найденного новостного материала.
 *
 * Сервис загружает и извлекает статью, выполняет AI-анализ, проверяет актуальность
 * и дубли, ставит внешнюю публикацию в очередь и журналирует каждый этап обработки.
 */
final class NewsPipeline
{
    /**
     * Получает все специализированные сервисы и репозитории, необходимые для прохождения
     * материала от загрузки страницы до готового поста и технического журнала.
     */
    public function __construct(
        private readonly HttpFetcher $http,
        private readonly ArticleExtractor $extractor,
        private readonly HashtagNormalizer $hashtagNormalizer,
        private readonly AIProvider $ai,
        private readonly AgentSettings $settings,
        private readonly SourceItemRepository $sourceItems,
        private readonly NewsCategoryRepository $categories,
        private readonly ItemAnalysisRepository $itemAnalyses,
        private readonly ItemDuplicateRepository $itemDuplicates,
        private readonly PublicationPostRepository $publicationPosts,
        private readonly ProcessingLogRepository $processingLogs,
        private readonly KaboomPublicationQueue $publicationQueue,
    ) {}

    /**
     * Последовательно обрабатывает материал и возвращает существующую публикацию либо `null`.
     *
     * Новый материал после проверок направляется в отдельную очередь Kaboom. Локальный пост
     * появится позже, только после успешного ответа внешнего API. Ручной режим обходит лишь
     * настройку автоматической публикации и не отключает проверки содержания.
     */
    public function process(SourceItem $item, bool $manualPublication = false): ?PublicationPost
    {
        if ($existing = $this->publicationPosts->findBySourceItemId((int) $item->getKey())) {
            return $existing;
        }
        if ($item->isQueuedForPublication()) {
            return null;
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
            $fallback = is_array($fallback) ? $fallback : [];
            $article = $this->extractor->extract($fetched->body, $fetched->url, [
                ...$fallback,
                'source_name' => $item->source->name,
            ]);
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
            $hashtags = $this->hashtagNormalizer->normalize($analysis->hashtags, $category?->hashtag);

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

            if (! $manualPublication && ! $this->settings->automaticPublication()) {
                $item = $this->sourceItems->update($item, SourceItemData::fromArray([
                    'status' => 'analyzed',
                    'rejection_reason' => SourceItem::MANUAL_PUBLICATION_REASON,
                ]));
                $this->log(
                    $item,
                    $correlationId,
                    'decision',
                    'pending',
                    hrtime(true),
                    SourceItem::MANUAL_PUBLICATION_REASON,
                );

                return null;
            }

            if ($manualPublication) {
                $this->log($item, $correlationId, 'decision', 'success', hrtime(true), 'manual_publication');
            }

            $publishStarted = hrtime(true);
            if (! $this->publicationQueue->enqueue($item, $correlationId)) {
                $item = $item->refresh();
                $this->log(
                    $item,
                    $correlationId,
                    'publish',
                    'pending',
                    $publishStarted,
                    'publication_state_changed',
                );

                return null;
            }
            $item = $item->refresh();
            $this->log($item, $correlationId, 'publish', 'pending', $publishStarted, 'kaboom_queued');
            $this->log($item, $correlationId, 'pipeline', 'pending', $pipelineStarted, 'kaboom_queued');

            return null;
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
     * Ищет точный дубликат по хешам, а затем семантически сравнивает подходящие материалы.
     *
     * Точное совпадение не требует обращения к AI, а семантические кандидаты ограничиваются
     * той же категорией и близким временным диапазоном для сокращения числа запросов.
     *
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

    /**
     * Переводит материал в заданный статус отклонения, сохраняет код причины и журналирует решение.
     *
     * Возвращаемый `null` позволяет вызывающему методу сразу завершить конвейер.
     */
    private function reject(SourceItem $item, string $correlationId, string $status, string $reason): null
    {
        $item = $this->sourceItems->update($item, SourceItemData::fromArray([
            'status' => $status,
            'rejection_reason' => $reason,
        ]));
        $this->log($item, $correlationId, 'decision', 'rejected', hrtime(true), $reason);

        return null;
    }

    /**
     * Проверяет, входит ли дата публикации в настроенное окно актуальности и не находится далеко в будущем.
     */
    private function isActual(CarbonInterface $date): bool
    {
        return $date->greaterThanOrEqualTo(now()->utc()->subHours($this->settings->maxNewsAgeHours()))
            && $date->lessThanOrEqualTo(now()->utc()->addHour());
    }

    /**
     * Возвращает URL изображения только после SSRF-проверки его публичной доступности.
     *
     * Небезопасное или некорректное изображение отбрасывается, не прерывая обработку всей статьи.
     */
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

    /**
     * Оставляет только безопасные диагностические HTTP-заголовки для метаданных извлечения.
     *
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function safeHeaders(array $headers): array
    {
        return array_intersect_key($headers, array_flip(['Content-Type', 'Last-Modified', 'ETag']));
    }

    /**
     * Сохраняет унифицированное событие этапа конвейера с длительностью, причиной,
     * ошибкой, активным AI-провайдером и ссылками на связанные сущности.
     */
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
