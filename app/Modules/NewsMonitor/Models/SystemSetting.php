<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Models;

final class SystemSetting extends NewsModel
{
    protected static string $newsTable = 'settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_secret' => 'boolean',
        ];
    }
}
