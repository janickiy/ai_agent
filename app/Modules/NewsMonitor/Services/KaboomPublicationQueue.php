<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Services;

use App\Jobs\PublishKaboomPost;
use App\Modules\NewsMonitor\Models\SourceItem;
use App\Modules\NewsMonitor\Repositories\Pipeline\SourceItemRepository;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Str;
use Throwable;

/**
 * Надёжно переводит материалы в состояние ожидания Kaboom и отправляет queue job.
 *
 * Сервис централизует CAS-переход состояния, освобождение уникальной Redis-блокировки
 * при ошибке брокера и повторную постановку давно зависших заданий.
 */
final readonly class KaboomPublicationQueue
{
    /**
     * Получает репозиторий исходных материалов для атомарных переходов состояния.
     */
    public function __construct(private SourceItemRepository $sourceItems) {}

    /**
     * Ставит проанализированный материал в очередь и откатывает состояние при ошибке Redis.
     *
     * Возвращает `false`, если другой процесс уже изменил материал и текущее задание
     * не должно отправляться повторно.
     *
     * @throws Throwable
     */
    public function enqueue(SourceItem $item, ?string $correlationId = null): bool
    {
        $previousStatus = (string) $item->status;
        $previousReason = $item->rejection_reason === null ? null : (string) $item->rejection_reason;
        $queuedItem = $this->sourceItems->markPublicationQueued($item);
        if ($queuedItem === null) {
            return false;
        }

        try {
            $this->dispatch($queuedItem, $correlationId);
        } catch (Throwable $exception) {
            $this->sourceItems->restoreAfterPublicationDispatchFailure(
                $queuedItem,
                $previousStatus,
                $previousReason,
            );

            throw $exception;
        }

        return true;
    }

    /**
     * Повторно отправляет задание для материала, давно оставшегося в состоянии очереди.
     *
     * UID и распределённая блокировка содержимого делают повтор безопасным, даже если
     * исходный Redis push был принят, но приложение не получило подтверждение.
     *
     * @throws Throwable
     */
    public function recover(SourceItem $item, ?string $correlationId = null): bool
    {
        $item = $item->refresh();
        if (! $item->isQueuedForPublication()) {
            return false;
        }

        $this->dispatch($item, $correlationId);

        return true;
    }

    /**
     * Отправляет уникальное задание и освобождает его lock, если Redis push завершился ошибкой.
     *
     * @throws Throwable
     */
    private function dispatch(SourceItem $item, ?string $correlationId): void
    {
        $job = new PublishKaboomPost(
            (int) $item->getKey(),
            $correlationId ?? (string) Str::uuid(),
        );

        try {
            dispatch($job)->afterCommit();
        } catch (Throwable $exception) {
            (new UniqueLock($job->uniqueVia()))->release($job);

            throw $exception;
        }
    }
}
