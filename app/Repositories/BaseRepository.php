<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\DataTransferObject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

/**
 * @template TModel of Model
 * @template TDto of DataTransferObject
 *
 * @implements RepositoryInterface<TModel, TDto>
 */
abstract class BaseRepository implements RepositoryInterface
{
    /** @param TModel $model */
    public function __construct(protected readonly Model $model)
    {
        $this->assertModel($model);
    }

    /** @return class-string<TModel> */
    abstract protected function modelClass(): string;

    /** @return non-empty-list<class-string<TDto>> */
    abstract protected function dtoClasses(): array;

    /** @return TModel|null */
    public function find(int|string $id): ?Model
    {
        /** @var TModel|null $model */
        $model = $this->query()->find($id);

        return $model;
    }

    /**
     * @param  TDto  $dto
     * @return TModel
     */
    public function create(DataTransferObject $dto): Model
    {
        $this->assertDto($dto);

        /** @var TModel $model */
        $model = $this->model->newInstance($dto->toArray());
        $this->assertModel($model);
        $model->saveOrFail();

        return $model->refresh();
    }

    /**
     * @param  TModel  $model
     * @param  TDto  $dto
     * @return TModel
     */
    public function update(Model $model, DataTransferObject $dto): Model
    {
        $this->assertModel($model);
        $this->assertDto($dto);

        $model->fill($dto->toArray());
        $model->saveOrFail();

        return $model->refresh();
    }

    /** @param TModel $model */
    public function delete(Model $model): void
    {
        $this->assertModel($model);

        if ($model->deleteOrFail() !== true) {
            throw new RuntimeException(sprintf('Unable to delete model %s.', $model::class));
        }
    }

    /** @return Builder<TModel> */
    protected function query(): Builder
    {
        /** @var Builder<TModel> $query */
        $query = $this->model->newQuery();

        return $query;
    }

    /** @phpstan-assert TModel $model */
    protected function assertModel(Model $model): void
    {
        $expected = $this->modelClass();

        if (! $model instanceof $expected) {
            throw new InvalidArgumentException(sprintf(
                '%s expects model %s; %s given.',
                static::class,
                $expected,
                $model::class,
            ));
        }
    }

    /** @phpstan-assert TDto $dto */
    protected function assertDto(DataTransferObject $dto): void
    {
        foreach ($this->dtoClasses() as $expected) {
            if ($dto instanceof $expected) {
                return;
            }
        }

        throw new InvalidArgumentException(sprintf(
            '%s expects DTO %s; %s given.',
            static::class,
            implode('|', $this->dtoClasses()),
            $dto::class,
        ));
    }
}
