<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\NewsMonitor\Models\NewsCategory;
use App\NewsMonitor\Models\Source;
use Illuminate\Database\Seeder;

final class NewsSourceSeeder extends Seeder
{
    public function run(): void
    {
        $categoryIds = NewsCategory::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        foreach (config('news_sources.sources', []) as $catalogSource) {
            $domain = (string) $catalogSource['domain'];
            $source = Source::query()->firstOrCreate(
                ['domain' => $domain],
                [
                    'name' => $catalogSource['name'],
                    'type' => 'rss',
                    'source_class' => $catalogSource['source_class'],
                    'trust_score' => $catalogSource['trust_score'],
                    'base_url' => "https://{$domain}",
                    'feed_url' => null,
                    'is_active' => true,
                    'is_trusted' => $catalogSource['trust_score'] >= 85,
                    'is_allowed' => true,
                    'poll_interval_minutes' => $catalogSource['poll_interval_minutes'],
                    'request_limit' => 30,
                    'timeout_seconds' => 20,
                    'max_attempts' => 3,
                    'status' => 'catalog',
                    'last_error' => null,
                    'next_poll_at' => null,
                ],
            );
            $source->update([
                'name' => $catalogSource['name'],
                'source_class' => $catalogSource['source_class'],
                'trust_score' => $catalogSource['trust_score'],
                'base_url' => "https://{$domain}",
                'is_trusted' => $catalogSource['trust_score'] >= 85,
                'poll_interval_minutes' => $catalogSource['poll_interval_minutes'],
            ]);

            $source->categories()->sync($categoryIds);
        }
    }
}
