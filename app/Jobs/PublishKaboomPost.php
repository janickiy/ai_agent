<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Modules\NewsMonitor\Publishing\KaboomPublicationException;
use App\Modules\NewsMonitor\Services\KaboomPublisher;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Публикует один проверенный материал в Kaboom в отдельной фоновой очереди.
 *
 * Уникальность по исходному материалу не допускает параллельных доставок, а стабильный
 * канонический UID делает повтор после сетевого сбоя безопасным для внешней ленты.
 */
final class PublishKaboomPost implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 180;

    public int $uniqueFor = 900;

    /** @var list<int> */
    public array $backoff = [30, 120, 300, 900];

    /**
     * Создаёт задание и направляет его в изолированную очередь внешней публикации.
     */
    public function __construct(
        public readonly int $sourceItemId,
        public readonly ?string $correlationId = null,
    ) {
        $this->onQueue('publishing');
    }

    /**
     * Возвращает стабильный ключ блокировки задания для одного исходного материала.
     */
    public function uniqueId(): string
    {
        return 'kaboom:'.$this->sourceItemId;
    }

    /**
     * Хранит блокировку уникальности задания в обязательном Redis, независимо от CACHE_STORE.
     */
    public function uniqueVia(): Repository
    {
        return Cache::store(app()->environment('testing') ? 'array' : 'redis');
    }

    /**
     * Выполняет отправку и немедленно завершает без повторов при постоянной ошибке контракта.
     *
     * Временные ответы 409/429/5xx и сетевые ошибки пробрасываются механизму очереди.
     *
     * @throws KaboomPublicationException
     * @throws Throwable
     */
    public function handle(KaboomPublisher $publisher): void
    {
        try {
            $publisher->publish($this->sourceItemId, $this->attempts(), $this->correlationId);
        } catch (KaboomPublicationException $exception) {
            if ($exception->isRetryable()) {
                throw $exception;
            }

            $this->fail($exception);
        }
    }

    /**
     * Фиксирует окончательную ошибку после исчерпания попыток или постоянного отказа API.
     */
    public function failed(?Throwable $exception): void
    {
        if ($exception === null) {
            return;
        }

        app(KaboomPublisher::class)->recordFailure(
            $this->sourceItemId,
            $this->attempts(),
            $exception,
            $this->correlationId,
        );
    }
}
