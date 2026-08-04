<?php

declare(strict_types=1);

namespace App\Enums;

enum ProcessingStatus: string
{
    case Success = 'success';
    case Error = 'error';
    case Rejected = 'rejected';
    case Pending = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Успешно',
            self::Error => 'Ошибка',
            self::Rejected => 'Отклонено',
            self::Pending => 'Ожидание',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Success => 'success',
            self::Error => 'danger',
            self::Rejected => 'warning',
            self::Pending => 'info',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }
}
