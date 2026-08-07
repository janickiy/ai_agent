<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Services;

use App\DTO\Pipeline\ItemDuplicateData;
use App\DTO\Pipeline\ProcessingLogData;
use App\DTO\Pipeline\PublicationPostData;
use App\DTO\Pipeline\SourceItemData;
use App\DTO\Publishing\KaboomPublicationData;
use App\Modules\NewsMonitor\Models\PublicationPost;
use App\Modules\NewsMonitor\Models\SourceItem;
use App\Modules\NewsMonitor\Publishing\KaboomClient;
use App\Modules\NewsMonitor\Publishing\KaboomPublicationException;
use App\Modules\NewsMonitor\Repositories\Pipeline\ItemDuplicateRepository;
use App\Modules\NewsMonitor\Repositories\Pipeline\ProcessingLogRepository;
use App\Modules\NewsMonitor\Repositories\Pipeline\PublicationPostRepository;
use App\Modules\NewsMonitor\Repositories\Pipeline\SourceItemRepository;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Координирует отправку подготовленного материала в Kaboom и локальную фиксацию успеха.
 *
 * До ответа HTTP 200/201 сервис не создаёт строку `publishing_publication_posts`.
 * После подтверждения он атомарно дублирует опубликованные поля, принимает исходный
 * материал и сохраняет безопасные метаданные внешнего ответа в техническом журнале.
 */
final readonly class KaboomPublisher
{
    private const CONTENT_LOCK_SECONDS = 210;

    private const CONTENT_LOCK_WAIT_SECONDS = 15;

    /**
     * Получает клиент Kaboom и репозитории для чтения и записи публикационных данных.
     */
    public function __construct(
        private KaboomClient $client,
        private SourceItemRepository $sourceItems,
        private PublicationPostRepository $publicationPosts,
        private ProcessingLogRepository $processingLogs,
        private ItemDuplicateRepository $itemDuplicates,
    ) {}

    /**
     * Публикует материал во внешней ленте и только после успеха создаёт локальный пост.
     *
     * Повторный вызов для уже сохранённой публикации не обращается к Kaboom и возвращает
     * существующую строку, что дополняет внешнюю идемпотентность по UID.
     *
     * @throws KaboomPublicationException
     * @throws Throwable
     */
    public function publish(
        int $sourceItemId,
        int $attempt = 1,
        ?string $correlationId = null,
    ): PublicationPost {
        if ($existing = $this->publicationPosts->findBySourceItemId($sourceItemId)) {
            return $existing;
        }

        $item = $this->sourceItems->findForPublication($sourceItemId);
        if ($item === null) {
            throw new KaboomPublicationException('Материал для публикации больше не существует.', false);
        }
        if (! $item->isQueuedForPublication()) {
            throw new KaboomPublicationException(
                'Материал ещё не подтверждён как поставленный в очередь Kaboom.',
                true,
            );
        }

        $contentHash = trim((string) $item->content_hash);
        if ($contentHash === '') {
            throw new KaboomPublicationException('У материала отсутствует контрольный хеш содержимого.', false);
        }

        try {
            return Cache::store('redis')->lock(
                'kaboom-publication-content:'.$contentHash,
                self::CONTENT_LOCK_SECONDS,
            )->block(
                self::CONTENT_LOCK_WAIT_SECONDS,
                fn (): PublicationPost => $this->publishWhileLocked(
                    $sourceItemId,
                    $attempt,
                    $correlationId,
                ),
            );
        } catch (LockTimeoutException $exception) {
            throw new KaboomPublicationException(
                'Другой материал с тем же содержимым ещё публикуется в Kaboom.',
                true,
                $exception,
            );
        }
    }

    /**
     * Повторно проверяет материал под распределённой блокировкой и выполняет отправку.
     *
     * Блокировка по хешу содержимого закрывает гонку между разными URL одной новости:
     * только первое задание обращается к Kaboom, остальные становятся дублями.
     *
     * @throws KaboomPublicationException
     * @throws Throwable
     */
    private function publishWhileLocked(
        int $sourceItemId,
        int $attempt,
        ?string $correlationId,
    ): PublicationPost {
        if ($existing = $this->publicationPosts->findBySourceItemId($sourceItemId)) {
            return $existing;
        }

        $item = $this->sourceItems->findForPublication($sourceItemId);
        if ($item === null) {
            throw new KaboomPublicationException('Материал для публикации больше не существует.', false);
        }
        if (! $item->isQueuedForPublication()) {
            throw new KaboomPublicationException(
                'Материал ещё не подтверждён как поставленный в очередь Kaboom.',
                true,
            );
        }

        $contentHash = (string) $item->content_hash;
        $duplicate = $this->publicationPosts->findByContentHash($contentHash);
        if ($duplicate !== null) {
            $item = DB::transaction(function () use ($item, $duplicate): SourceItem {
                $item = $this->sourceItems->update($item, SourceItemData::fromArray([
                    'status' => 'duplicate',
                    'rejection_reason' => 'content_hash',
                ]));
                $this->itemDuplicates->upsertForSourceItem(new ItemDuplicateData(
                    sourceItemId: (int) $item->getKey(),
                    originalSourceItemId: (int) $duplicate->source_item_id,
                    method: 'content_hash',
                    similarity: 1.0,
                    algorithmVersion: 'sha256-v1',
                    meta: ['publication_post_id' => (int) $duplicate->getKey()],
                ));

                return $item;
            });
            $this->recordLog(
                item: $item,
                status: 'rejected',
                attempt: $attempt,
                started: hrtime(true),
                reason: 'content_hash',
                publicationPostId: (int) $duplicate->getKey(),
                context: ['original_source_item_id' => (int) $duplicate->source_item_id],
                correlationId: $correlationId,
            );

            return $duplicate;
        }

        $publication = $this->publicationData($item);
        $started = hrtime(true);
        $result = $this->client->publish($publication);
        $publishedAt = now()->utc();

        $post = DB::transaction(function () use (
            $item,
            $publication,
            $result,
            $publishedAt,
        ): PublicationPost {
            $existing = $this->publicationPosts->findBySourceItemId((int) $item->getKey());
            if ($existing !== null) {
                return $existing;
            }

            $post = $this->publicationPosts->firstOrCreateForSourceItem(new PublicationPostData(
                sourceItemId: (int) $item->getKey(),
                uid: $publication->uid,
                idempotencyKey: hash(
                    'sha256',
                    $item->getKey().':'.$publication->uid.':'.(string) $item->content_hash,
                ),
                sourceUrl: $publication->url,
                sourceName: $publication->publicationSource,
                sourcePublishedAt: $publication->published,
                titleOriginal: $publication->title,
                descriptionOriginal: $publication->shortDescription,
                fullDescriptionOriginal: $publication->fullDescription,
                imageUrl: $publication->imageUrl,
                imageStorageKey: null,
                readMoreLabel: 'Читать в источнике',
                categoryId: (int) $item->analysis->category->getKey(),
                hashtags: $publication->hashtags,
                contentHash: (string) $item->content_hash,
                status: 'exported',
                validationMeta: [
                    'title_hash' => hash('sha256', $publication->title),
                    'description_hash' => hash('sha256', $publication->shortDescription),
                    'rules_version' => 'kaboom-publisher-v1',
                    'copied_fields_unchanged' => true,
                    'kaboom' => $result->toArray(),
                ],
                readyAt: $publishedAt,
                exportedAt: $publishedAt,
            ));

            $this->sourceItems->update($item, SourceItemData::fromArray([
                'status' => 'accepted',
                'rejection_reason' => null,
            ]));

            return $post;
        }, attempts: 3);

        $this->recordLog(
            item: $item,
            status: 'success',
            attempt: $attempt,
            started: $started,
            reason: $result->created ? 'kaboom_created' : 'kaboom_updated',
            publicationPostId: (int) $post->getKey(),
            context: ['kaboom' => $result->toArray()],
            correlationId: $correlationId,
        );

        return $post;
    }

    /**
     * Отмечает окончательно не опубликованный материал и записывает причину в журнал.
     *
     * Метод вызывается очередью только после постоянной ошибки либо исчерпания повторов.
     */
    public function recordFailure(
        int $sourceItemId,
        int $attempt,
        Throwable $exception,
        ?string $correlationId = null,
    ): void {
        if ($this->publicationPosts->findBySourceItemId($sourceItemId) !== null) {
            return;
        }

        $item = $this->sourceItems->findForPublication($sourceItemId);
        if ($item === null) {
            return;
        }

        $item = $this->sourceItems->markPublicationFailed($item);
        if (
            $item->status !== 'analyzed'
            || $item->rejection_reason !== SourceItem::PUBLICATION_FAILED_REASON
        ) {
            return;
        }

        $this->recordLog(
            item: $item,
            status: 'error',
            attempt: $attempt,
            started: hrtime(true),
            reason: SourceItem::PUBLICATION_FAILED_REASON,
            error: $exception->getMessage(),
            context: ['kaboom' => ['endpoint' => KaboomSettings::ENDPOINT]],
            correlationId: $correlationId,
        );
    }

    /**
     * Формирует точное сопоставление полей исходного материала с multipart-контрактом Kaboom.
     *
     * @throws KaboomPublicationException
     */
    private function publicationData(SourceItem $item): KaboomPublicationData
    {
        $analysis = $item->analysis;
        $category = $analysis?->category;
        $uid = (string) $item->canonical_url;
        $title = (string) $item->title_original;
        $fullDescription = (string) $item->body_text;
        $shortDescription = (string) $item->description_original;

        if (trim($uid) === '' || mb_strlen($uid) > 512) {
            throw new KaboomPublicationException('UID публикации пуст или превышает 512 символов.', false);
        }
        if (trim($title) === '' || $item->source_published_at === null) {
            throw new KaboomPublicationException('У материала отсутствуют заголовок или дата публикации.', false);
        }
        if (trim($fullDescription) === '' || trim($shortDescription) === '') {
            throw new KaboomPublicationException(
                'У материала отсутствует полное или краткое описание.',
                false,
            );
        }
        if ($item->source === null || $analysis === null || $category === null) {
            throw new KaboomPublicationException('У материала отсутствуют источник, анализ или категория.', false);
        }
        if (! is_array($analysis->hashtags) || $analysis->hashtags === []) {
            throw new KaboomPublicationException('У материала отсутствуют подготовленные хэштеги.', false);
        }
        if (trim((string) $item->content_hash) === '') {
            throw new KaboomPublicationException('У материала отсутствует контрольный хеш содержимого.', false);
        }

        /** @var list<string> $hashtags */
        $hashtags = array_values(array_filter(
            array_map(static fn (mixed $hashtag): string => (string) $hashtag, $analysis->hashtags),
            static fn (string $hashtag): bool => trim($hashtag) !== '',
        ));
        if ($hashtags === []) {
            throw new KaboomPublicationException('У материала отсутствуют непустые хэштеги.', false);
        }
        if (count($hashtags) > 7) {
            throw new KaboomPublicationException('Материал содержит больше семи хэштегов.', false);
        }

        return new KaboomPublicationData(
            uid: $uid,
            title: $title,
            published: $item->source_published_at,
            fullDescription: $fullDescription,
            shortDescription: $shortDescription,
            url: $uid,
            publicationSource: (string) $item->source->name,
            category: (string) $category->name,
            hashtags: $hashtags,
            imageUrl: $item->image_url === null ? null : (string) $item->image_url,
        );
    }

    /**
     * Добавляет унифицированную запись этапа внешней публикации без секретных данных.
     *
     * @param  array<string, mixed>  $context
     */
    private function recordLog(
        SourceItem $item,
        string $status,
        int $attempt,
        int $started,
        string $reason,
        ?int $publicationPostId = null,
        ?string $error = null,
        array $context = [],
        ?string $correlationId = null,
    ): void {
        $this->processingLogs->record(new ProcessingLogData(
            correlationId: $correlationId ?? (string) Str::uuid(),
            sourceId: (int) $item->source_id,
            sourceItemId: (int) $item->getKey(),
            publicationPostId: $publicationPostId,
            stage: 'publish',
            status: $status,
            attempt: max(1, $attempt),
            durationMs: (int) ((hrtime(true) - $started) / 1_000_000),
            reasonCode: $reason,
            errorMessage: $error === null ? null : Str::limit($error, 1000),
            context: $context,
            startedAt: now()->utc(),
            finishedAt: now()->utc(),
        ));
    }
}
