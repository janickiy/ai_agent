<?php

declare(strict_types=1);

namespace App\Modules\Admin\Repositories;

use App\DTO\Admin\AdministratorData;
use App\Models\User;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

/** @extends BaseRepository<User, AdministratorData> */
final class AdministratorRepository extends BaseRepository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /** @return Builder<User> */
    public function forDataTable(): Builder
    {
        return $this->query()->where('role', 'administrator');
    }

    public function activeCountForUpdate(): int
    {
        return $this->query()
            ->where('role', 'administrator')
            ->where('is_active', true)
            ->where('admin_access', true)
            ->orderBy($this->model->getQualifiedKeyName())
            ->lockForUpdate()
            ->get()
            ->count();
    }

    public function lockForUpdate(User $administrator): User
    {
        $this->assertModel($administrator);

        /** @var User $locked */
        $locked = $this->query()
            ->whereKey($administrator->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $locked;
    }

    protected function modelClass(): string
    {
        return User::class;
    }

    /** @return non-empty-list<class-string<AdministratorData>> */
    protected function dtoClasses(): array
    {
        return [AdministratorData::class];
    }
}
