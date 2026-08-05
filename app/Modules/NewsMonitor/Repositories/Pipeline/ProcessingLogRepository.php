<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Repositories\Pipeline;

use App\DTO\Pipeline\ProcessingLogData;
use App\Modules\NewsMonitor\Models\ProcessingLog;
use App\Repositories\BaseRepository;

/**
 * Сохраняет технические события прохождения материалов по этапам конвейера.
 *
 * Выделенный репозиторий обеспечивает типизированную запись журнала и отделяет
 * диагностическое хранение от бизнес-логики сервисов мониторинга.
 *
 * @extends BaseRepository<ProcessingLog, ProcessingLogData>
 */
final class ProcessingLogRepository extends BaseRepository
{
    public function __construct(ProcessingLog $model)
    {
        parent::__construct($model);
    }

    /**
     * Сохраняет типизированное событие обработки через базовую CRUD-операцию.
     *
     * Отдельное имя метода подчёркивает append-only назначение журнала для диагностики конвейера.
     */
    public function record(ProcessingLogData $dto): ProcessingLog
    {
        /** @var ProcessingLog $log */
        $log = $this->create($dto);

        return $log;
    }

    /**
     * Указывает базовому репозиторию модель журнала, допустимую для операций с данными.
     *
     * @return class-string<ProcessingLog>
     */
    protected function modelClass(): string
    {
        return ProcessingLog::class;
    }

    /**
     * Определяет DTO, который может использоваться для создания записей журнала.
     *
     * @return non-empty-list<class-string<ProcessingLogData>>
     */
    protected function dtoClasses(): array
    {
        return [ProcessingLogData::class];
    }
}
