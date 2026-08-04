<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Models;

use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Eloquent\Model;

abstract class NewsModel extends Model
{
    protected static string $newsTable;

    public function getTable(): string
    {
        return NewsTables::name(static::$newsTable);
    }
}
