<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Repositories\Pipeline;

use App\DTO\Pipeline\PublicationPostData;
use App\Modules\NewsMonitor\Models\PublicationPost;
use App\Repositories\BaseRepository;

/** @extends BaseRepository<PublicationPost, PublicationPostData> */
final class PublicationPostRepository extends BaseRepository
{
    public function __construct(PublicationPost $model)
    {
        parent::__construct($model);
    }

    /**
     * @param int $sourceItemId
     * @return PublicationPost|null
     */
    public function findBySourceItemId(int $sourceItemId): ?PublicationPost
    {
        /** @var PublicationPost|null $post */
        $post = $this->query()->where('source_item_id', $sourceItemId)->first();

        return $post;
    }

    /**
     * @param PublicationPostData $dto
     * @return PublicationPost
     */
    public function firstOrCreateForSourceItem(PublicationPostData $dto): PublicationPost
    {
        $attributes = $dto->toArray();
        unset($attributes['source_item_id']);

        /** @var PublicationPost $post */
        $post = $this->query()->firstOrCreate(
            ['source_item_id' => $dto->sourceItemId],
            $attributes,
        );

        return $post;
    }

    protected function modelClass(): string
    {
        return PublicationPost::class;
    }

    /** @return non-empty-list<class-string<PublicationPostData>> */
    protected function dtoClasses(): array
    {
        return [PublicationPostData::class];
    }
}
