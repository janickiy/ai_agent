<?php

declare(strict_types=1);

namespace App\NewsMonitor\Services;

use App\NewsMonitor\Models\SystemSetting;

final class AgentSettings
{
    private const KEY = 'agent';

    /** @var array{collection_enabled: bool, automatic_publication: bool, max_news_age_hours: int, event_similarity_threshold: float}|null */
    private ?array $values = null;

    /** @return array{collection_enabled: bool, automatic_publication: bool, max_news_age_hours: int, event_similarity_threshold: float} */
    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        $stored = SystemSetting::query()->find(self::KEY)?->value;
        $values = array_replace($this->defaults(), is_array($stored) ? $stored : []);

        return $this->values = [
            'collection_enabled' => (bool) $values['collection_enabled'],
            'automatic_publication' => (bool) $values['automatic_publication'],
            'max_news_age_hours' => (int) $values['max_news_age_hours'],
            'event_similarity_threshold' => (float) $values['event_similarity_threshold'],
        ];
    }

    /** @param array{collection_enabled: bool, automatic_publication: bool, max_news_age_hours: int, event_similarity_threshold: float} $values */
    public function update(array $values): SystemSetting
    {
        $setting = SystemSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => $values, 'is_secret' => false],
        );
        $this->values = null;

        return $setting;
    }

    public function collectionEnabled(): bool
    {
        return $this->all()['collection_enabled'];
    }

    public function automaticPublication(): bool
    {
        return $this->all()['automatic_publication'];
    }

    public function maxNewsAgeHours(): int
    {
        return $this->all()['max_news_age_hours'];
    }

    public function eventSimilarityThreshold(): float
    {
        return $this->all()['event_similarity_threshold'];
    }

    /** @return array{collection_enabled: bool, automatic_publication: bool, max_news_age_hours: int, event_similarity_threshold: float} */
    private function defaults(): array
    {
        return [
            'collection_enabled' => true,
            'automatic_publication' => (bool) config('news.publication_output_enabled'),
            'max_news_age_hours' => max(1, (int) config('news.actuality_window_days') * 24),
            'event_similarity_threshold' => (float) config('news.semantic_duplicate_threshold'),
        ];
    }
}
