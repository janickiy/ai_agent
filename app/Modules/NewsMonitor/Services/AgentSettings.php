<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Services;

use App\DTO\Settings\AgentSettingsData;
use App\DTO\System\SystemSettingData;
use App\Modules\NewsMonitor\Models\SystemSetting;
use App\Modules\NewsMonitor\Repositories\System\SystemSettingRepository;

final class AgentSettings
{
    private const KEY = 'agent';

    /** @var array{automatic_publication: bool, max_news_age_hours: int, event_similarity_threshold: float}|null */
    private ?array $values = null;

    public function __construct(private readonly SystemSettingRepository $settings) {}

    /** @return array{automatic_publication: bool, max_news_age_hours: int, event_similarity_threshold: float} */
    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        $stored = $this->settings->find(self::KEY)?->value;
        $values = array_replace($this->defaults(), is_array($stored) ? $stored : []);

        return $this->values = [
            'automatic_publication' => (bool) $values['automatic_publication'],
            'max_news_age_hours' => (int) $values['max_news_age_hours'],
            'event_similarity_threshold' => (float) $values['event_similarity_threshold'],
        ];
    }

    public function update(AgentSettingsData $data): SystemSetting
    {
        $setting = $this->settings->put(SystemSettingData::fromArray([
            'key' => self::KEY,
            'value' => $data->toArray(),
            'is_secret' => false,
        ]));
        $this->values = null;

        return $setting;
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

    /** @return array{automatic_publication: bool, max_news_age_hours: int, event_similarity_threshold: float} */
    private function defaults(): array
    {
        return [
            'automatic_publication' => (bool) config('news.publication_output_enabled'),
            'max_news_age_hours' => max(1, (int) config('news.actuality_window_days') * 24),
            'event_similarity_threshold' => (float) config('news.semantic_duplicate_threshold'),
        ];
    }
}
