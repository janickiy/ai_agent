<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Models;

/**
 * Представляет таблицу `system_settings` с настраиваемыми параметрами приложения.
 *
 * Таблица хранит строковый ключ, JSON-значение и признак секретности. Здесь размещаются
 * общие настройки агента, публичные параметры AI и зашифрованные реквизиты провайдеров.
 */
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
