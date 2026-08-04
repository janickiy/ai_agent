<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Modules\NewsMonitor\Repositories\Pipeline\SourceItemRepository;
use App\Modules\NewsMonitor\Services\NewsPipeline;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProcessSourceItem implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 300;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [30, 120, 300, 900];

    public function __construct(public readonly int $sourceItemId)
    {
        $this->onQueue('analysis');
    }

    public function uniqueId(): string
    {
        return (string) $this->sourceItemId;
    }

    public function handle(NewsPipeline $pipeline, SourceItemRepository $sourceItems): void
    {
        $item = $sourceItems->findForProcessing($this->sourceItemId);
        if ($item === null) {
            return;
        }

        $pipeline->process($item);
    }
}
