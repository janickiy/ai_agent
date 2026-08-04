<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\DataTransferObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 * @template TDto of DataTransferObject
 */
interface RepositoryInterface
{
    /** @return TModel|null */
    public function find(int|string $id): ?Model;

    /**
     * @param  TDto  $dto
     * @return TModel
     */
    public function create(DataTransferObject $dto): Model;

    /**
     * @param  TModel  $model
     * @param  TDto  $dto
     * @return TModel
     */
    public function update(Model $model, DataTransferObject $dto): Model;

    /** @param TModel $model */
    public function delete(Model $model): void;
}
