<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\NewsMonitor\Repositories\Pipeline\SourceItemRepository;
use App\Modules\NewsMonitor\Services\KaboomPublicationQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Восстанавливает задания Kaboom, оставшиеся в БД без подтверждённой публикации.
 *
 * Команда является reconciliation-механизмом между MySQL и Redis и безопасно
 * повторяет доставку благодаря стабильному UID и уникальным блокировкам очереди.
 */
final class RecoverKaboomPublications extends Command
{
    protected $signature = 'news:recover-publications
        {--minutes=10 : Минимальный возраст зависшего задания}
        {--limit=100 : Максимум материалов за один запуск}';

    protected $description = 'Повторно ставит в очередь зависшие публикации Kaboom';

    /**
     * Получает репозиторий зависших материалов и сервис безопасной постановки в очередь.
     */
    public function __construct(
        private readonly SourceItemRepository $sourceItems,
        private readonly KaboomPublicationQueue $publicationQueue,
    ) {
        parent::__construct();
    }

    /**
     * Находит старые queued-материалы и повторно отправляет задания в Redis.
     */
    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $items = $this->sourceItems->queuedForPublicationRecovery(
            now()->utc()->subMinutes($minutes),
            $limit,
        );
        $queued = 0;
        $errors = 0;

        foreach ($items as $item) {
            try {
                if ($this->publicationQueue->recover($item, (string) Str::uuid())) {
                    $queued++;
                }
            } catch (Throwable $exception) {
                $errors++;
                report($exception);
            }
        }

        $this->info("Найдено: {$items->count()}; повторно поставлено: {$queued}; ошибок: {$errors}.");

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
