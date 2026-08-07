<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Modules\NewsMonitor\Repositories\Pipeline\SourceItemRepository;
use App\Modules\NewsMonitor\Services\NewsPipeline;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Выполняет полный pipeline обработки одного найденного новостного материала в фоновой очереди.
 *
 * Задание поддерживает обычный автоматический режим и явно запрошенную оператором
 * ручную публикацию, которая обходит только настройку автоматического создания постов.
 */
final class ProcessSourceItem implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 300;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [30, 120, 300, 900];

    /**
     * Создаёт задание для материала и направляет его в очередь анализа.
     *
     * Признак ручной публикации передаётся в pipeline после извлечения задания из очереди.
     */
    public function __construct(
        public readonly int $sourceItemId,
        public readonly bool $manualPublication = false,
    ) {
        $this->onQueue('analysis');
    }

    /**
     * Возвращает уникальный ключ задания с учётом режима запуска.
     *
     * Разделение ключей позволяет ручному запросу не потеряться, если для того же материала
     * уже ожидает обычное автоматическое задание.
     */
    public function uniqueId(): string
    {
        return ($this->manualPublication ? 'manual:' : 'automatic:').$this->sourceItemId;
    }

    /**
     * Загружает материал через репозиторий и передаёт его в сервис обработки.
     *
     * Если запись была удалена до запуска задания, метод безопасно завершает работу.
     */
    public function handle(NewsPipeline $pipeline, SourceItemRepository $sourceItems): void
    {
        $item = $sourceItems->findForProcessing($this->sourceItemId);
        if ($item === null) {
            return;
        }

        $pipeline->process($item, $this->manualPublication);
    }
}
