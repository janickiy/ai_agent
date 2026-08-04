<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\NewsMonitor\Services\SourceMonitor;
use Illuminate\Console\Command;

final class MonitorNews extends Command
{
    protected $signature = 'news:monitor {--source= : ID конкретного источника} {--force : Игнорировать next_poll_at}';

    protected $description = 'Получить новые публикации из активных открытых источников';

    public function handle(SourceMonitor $monitor): int
    {
        $sourceId = $this->option('source');
        $stats = $monitor->monitor(
            $sourceId === null ? null : (int) $sourceId,
            (bool) $this->option('force'),
        );

        $this->components->info(sprintf(
            'Источников: %d; новых URL: %d; ошибок: %d',
            $stats['sources'],
            $stats['discovered'],
            $stats['failed'],
        ));

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
