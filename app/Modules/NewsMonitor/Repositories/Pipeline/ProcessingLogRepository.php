<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Repositories\Pipeline;

use App\DTO\Pipeline\ProcessingLogData;
use App\Modules\NewsMonitor\Models\ProcessingLog;
use App\Repositories\BaseRepository;

/** @extends BaseRepository<ProcessingLog, ProcessingLogData> */
final class ProcessingLogRepository extends BaseRepository
{
    public function __construct(ProcessingLog $model)
    {
        parent::__construct($model);
    }

    /**
     * @param ProcessingLogData $dto
     * @return ProcessingLog
     */
    public function record(ProcessingLogData $dto): ProcessingLog
    {
        /** @var ProcessingLog $log */
        $log = $this->create($dto);

        return $log;
    }

    protected function modelClass(): string
    {
        return ProcessingLog::class;
    }

    /** @return non-empty-list<class-string<ProcessingLogData>> */
    protected function dtoClasses(): array
    {
        return [ProcessingLogData::class];
    }
}
