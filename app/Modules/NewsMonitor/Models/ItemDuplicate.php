<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Models;

final class ItemDuplicate extends NewsModel
{
    protected static string $newsTable = 'duplicates';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['similarity' => 'float', 'meta' => 'array'];
    }
}
