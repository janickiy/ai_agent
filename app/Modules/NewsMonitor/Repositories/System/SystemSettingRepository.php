<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Repositories\System;

use App\DTO\System\SystemSettingData;
use App\Modules\NewsMonitor\Models\SystemSetting;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/** @extends BaseRepository<SystemSetting, SystemSettingData> */
final class SystemSettingRepository extends BaseRepository
{
    public function __construct(SystemSetting $model)
    {
        parent::__construct($model);
    }

    public function put(SystemSettingData $dto): SystemSetting
    {
        return $this->upsert($dto);
    }

    public function upsert(SystemSettingData $dto): SystemSetting
    {
        return DB::transaction(function () use ($dto): SystemSetting {
            /** @var SystemSetting|null $setting */
            $setting = $this->query()
                ->whereKey($dto->key)
                ->lockForUpdate()
                ->first();

            if ($setting === null) {
                /** @var SystemSetting $created */
                $created = parent::create($dto);

                return $created;
            }

            /** @var SystemSetting $updated */
            $updated = parent::update($setting, $dto);

            return $updated;
        });
    }

    protected function modelClass(): string
    {
        return SystemSetting::class;
    }

    /** @return non-empty-list<class-string<SystemSettingData>> */
    protected function dtoClasses(): array
    {
        return [SystemSettingData::class];
    }
}
