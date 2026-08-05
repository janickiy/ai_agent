<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Services;

/**
 * Выполняет минимальную техническую очистку текстовых полей исходной новости.
 *
 * Сервис не переписывает редакционный текст: он удаляет HTML, управляющие символы
 * и лишние пробелы, сохраняя исходный смысл материала.
 */
final class ContentNormalizer
{
    /**
     * Очищает скопированное текстовое поле от HTML, управляющих символов и повторных пробелов.
     */
    public function copiedField(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    /**
     * Нормализует основной текст статьи по тем же безопасным правилам, что и остальные поля.
     *
     * Отдельный метод сохраняет явную точку расширения для будущей обработки тела материала.
     */
    public function body(string $value): string
    {
        return $this->copiedField($value);
    }
}
