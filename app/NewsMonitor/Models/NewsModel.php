<?php

declare(strict_types=1);

namespace App\NewsMonitor\Models;

use App\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Eloquent\Model;

abstract class NewsModel extends Model
{
    protected static string $newsTable;

    public function getTable(): string
    {
        return NewsTables::name(static::$newsTable);
    }
}
