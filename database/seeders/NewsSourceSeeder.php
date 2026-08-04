<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\NewsMonitor\Models\NewsCategory;
use App\Modules\NewsMonitor\Models\Source;
use Illuminate\Database\Seeder;

final class NewsSourceSeeder extends Seeder
{
    public function run(): void
    {
        $categoryIdsByCode = NewsCategory::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('id', 'code');
        $allCategoryIds = $categoryIdsByCode->values()->all();

        foreach (config('news_sources.sources', []) as $catalogSource) {
            $domain = (string) $catalogSource['domain'];
            $source = Source::query()->where('domain', $domain)->first();
            if ($source === null && ($catalogSource['legacy_domains'] ?? []) !== []) {
                $source = Source::query()
                    ->whereIn('domain', $catalogSource['legacy_domains'])
                    ->first();
                $source?->update(['domain' => $domain]);
            }

            if ($source === null) {
                $source = Source::query()->create([
                    'name' => $catalogSource['name'],
                    'domain' => $domain,
                    'type' => $catalogSource['type'] ?? 'rss',
                    'source_class' => $catalogSource['source_class'],
                    'trust_score' => $catalogSource['trust_score'],
                    'base_url' => "https://{$domain}",
                    'feed_url' => $catalogSource['feed_url'] ?? null,
                    'is_active' => $catalogSource['is_active'] ?? true,
                    'is_trusted' => $catalogSource['trust_score'] >= 85,
                    'is_allowed' => true,
                    'poll_interval_minutes' => $catalogSource['poll_interval_minutes'],
                    'request_limit' => 30,
                    'timeout_seconds' => 20,
                    'max_attempts' => 3,
                    'status' => 'catalog',
                    'last_error' => null,
                    'next_poll_at' => null,
                ]);
            }

            $updates = [
                'name' => $catalogSource['name'],
                'source_class' => $catalogSource['source_class'],
                'trust_score' => $catalogSource['trust_score'],
                'base_url' => "https://{$domain}",
                'is_trusted' => $catalogSource['trust_score'] >= 85,
                'poll_interval_minutes' => $catalogSource['poll_interval_minutes'],
            ];
            if ($source->feed_url === null && isset($catalogSource['feed_url'])) {
                $updates['type'] = $catalogSource['type'] ?? 'rss';
                $updates['feed_url'] = $catalogSource['feed_url'];
            }
            if ($source->feed_url === null && array_key_exists('is_active', $catalogSource)) {
                $updates['is_active'] = (bool) $catalogSource['is_active'];
            }
            $source->update($updates);

            $categoryCodes = $catalogSource['categories'] ?? null;
            $categoryIds = $categoryCodes === null
                ? $allCategoryIds
                : $categoryIdsByCode->only($categoryCodes)->values()->all();
            $source->categories()->sync($categoryIds);
        }
    }
}
