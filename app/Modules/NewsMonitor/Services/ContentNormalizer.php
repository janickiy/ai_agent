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
    private const MIN_DUPLICATE_BLOCK_LENGTH = 24;

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
        $value = preg_replace('/<br\s*\/?>|<\/(?:p|div|li|blockquote|h[1-6])>/iu', "\n", $value) ?? $value;
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', $value) ?? $value;
        $value = preg_replace('/[\t\x{00A0} ]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/ *\n */u', "\n", $value) ?? $value;
        $value = preg_replace('/\n{3,}/u', "\n\n", $value) ?? $value;

        return $this->withoutRepeatedBlocks(trim($value));
    }

    /**
     * Подготавливает полный текст к публикации рядом с отдельным кратким описанием.
     *
     * Метод очищает точные повторы внутри статьи и удаляет лид из начала полного
     * текста, только когда он дословно совпадает с уже передаваемым кратким описанием.
     * Если после удаления ничего не останется, исходный текст сохраняется.
     */
    public function publicationBody(string $body, string $shortDescription): string
    {
        $body = $this->body($body);
        $shortDescription = $this->copiedField($shortDescription);
        if ($body === '' || mb_strlen($shortDescription) < 12) {
            return $body;
        }

        $pattern = str_replace(' ', '\\s+', preg_quote($shortDescription, '/'));
        if (preg_match('/^\s*'.$pattern.'(?=\s|$)/iu', $body, $match) !== 1) {
            return $body;
        }

        $remaining = ltrim(substr($body, strlen($match[0])));

        return $remaining === '' ? $body : $remaining;
    }

    /**
     * Удаляет повторные текстовые блоки, которые парсер мог получить из галереи,
     * мобильной копии или вложенной HTML-разметки, оставляя первое вхождение.
     *
     * Короткие строки не затрагиваются: даты, подписи разделов и другие небольшие
     * элементы могут законно встречаться в статье несколько раз.
     */
    private function withoutRepeatedBlocks(string $value): string
    {
        $lines = preg_split('/\n/u', $value) ?: [];
        $seen = [];
        $result = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                if ($result !== [] && end($result) !== '') {
                    $result[] = '';
                }

                continue;
            }

            $fingerprint = mb_strtolower(preg_replace('/\s+/u', ' ', $line) ?? $line);
            if (
                mb_strlen($fingerprint) >= self::MIN_DUPLICATE_BLOCK_LENGTH
                && isset($seen[$fingerprint])
            ) {
                continue;
            }

            if (mb_strlen($fingerprint) >= self::MIN_DUPLICATE_BLOCK_LENGTH) {
                $seen[$fingerprint] = true;
            }
            $result[] = $line;
        }

        while ($result !== [] && end($result) === '') {
            array_pop($result);
        }

        return implode("\n", $result);
    }
}
