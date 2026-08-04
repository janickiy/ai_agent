<?php

declare(strict_types=1);

namespace App\Enums;

enum ProcessingStage: string
{
    case Discovery = 'discovery';
    case Fetch = 'fetch';
    case Extract = 'extract';
    case Analyze = 'analyze';
    case Deduplicate = 'deduplicate';
    case Decision = 'decision';
    case Publish = 'publish';
    case Pipeline = 'pipeline';

    public function label(): string
    {
        return match ($this) {
            self::Discovery => 'Сбор',
            self::Fetch => 'Загрузка',
            self::Extract => 'Извлечение',
            self::Analyze => 'Анализ',
            self::Deduplicate => 'Проверка дублей',
            self::Decision => 'Решение',
            self::Publish => 'Публикация',
            self::Pipeline => 'Pipeline',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $stage) {
            $options[$stage->value] = $stage->label();
        }

        return $options;
    }
}
