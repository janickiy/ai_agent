<?php

declare(strict_types=1);

namespace App\NewsMonitor\AI\DTO;

final readonly class PublicationDraft
{
    /**
     * @param  list<string>  $hashtags
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $titleOriginal,
        public string $descriptionOriginal,
        public array $hashtags,
        public array $meta,
    ) {}
}
