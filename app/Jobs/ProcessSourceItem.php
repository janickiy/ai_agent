<?php

declare(strict_types=1);

namespace App\Jobs;

use App\NewsMonitor\Models\SourceItem;
use App\NewsMonitor\Services\NewsPipeline;
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

    public function handle(NewsPipeline $pipeline): void
    {
        $pipeline->process(SourceItem::query()->with('source')->findOrFail($this->sourceItemId));
    }
}
