<?php

declare(strict_types=1);

namespace App\NewsMonitor\Support;

use InvalidArgumentException;

final class NewsTables
{
    private const TABLES = [
        'categories' => ['catalog', 'news_categories'],
        'subjects' => ['catalog', 'subjects'],
        'sources' => ['catalog', 'sources'],
        'source_category' => ['catalog', 'source_category'],
        'source_items' => ['collector', 'source_items'],
        'analyses' => ['analysis', 'item_analyses'],
        'duplicates' => ['analysis', 'item_duplicates'],
        'events' => ['analysis', 'news_events'],
        'event_items' => ['analysis', 'news_event_items'],
        'posts' => ['publishing', 'publication_posts'],
        'processing_logs' => ['system', 'processing_logs'],
        'audit_logs' => ['system', 'audit_logs'],
        'settings' => ['system', 'settings'],
    ];

    public static function name(string $key): string
    {
        [$schema, $table] = self::TABLES[$key]
            ?? throw new InvalidArgumentException("Unknown news table: {$key}");

        return "{$schema}_{$table}";
    }
}
