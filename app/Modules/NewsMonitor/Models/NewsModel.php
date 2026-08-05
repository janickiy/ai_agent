<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Models;

use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Eloquent\Model;

/**
 * Базовая Eloquent-модель таблиц модуля мониторинга новостей.
 *
 * Класс не представляет отдельную таблицу. Он преобразует логический ключ таблицы
 * дочерней модели в фактическое имя с функциональным префиксом через `NewsTables`.
 */
abstract class NewsModel extends Model
{
    protected static string $newsTable;

    /**
     * Возвращает фактическое имя таблицы для текущей модели по её логическому ключу.
     *
     * Это централизует соглашение об именах и не позволяет моделям дублировать префиксы таблиц.
     */
    public function getTable(): string
    {
        return NewsTables::name(static::$newsTable);
    }
}
