<?php

declare(strict_types=1);

namespace App\Jobs;

use App\NewsMonitor\Services\SourceMonitor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RunNewsMonitor implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public int $uniqueFor = 600;

    public function handle(SourceMonitor $monitor): void
    {
        $monitor->monitor(force: true);
    }

    public function uniqueId(): string
    {
        return 'manual-news-monitor';
    }
}
