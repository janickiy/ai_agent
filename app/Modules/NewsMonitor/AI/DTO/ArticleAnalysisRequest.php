<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\AI\DTO;

final readonly class ArticleAnalysisRequest
{
    /**
     * @param  array<string, array{name: string, hashtag: string, keywords: list<string>}>  $categories
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $body,
        public array $categories,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'body' => $this->body,
            'categories' => $this->categories,
        ];
    }
}
