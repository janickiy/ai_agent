<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Services;

/**
 * Приводит хэштеги AI-провайдеров и справочника тематик к единому безопасному формату.
 *
 * Сервис удаляет фигурные скобки, кавычки и прочую пунктуацию, восстанавливает
 * единственный символ `#`, убирает повторы и ограничивает количество тегов публикации.
 */
final class HashtagNormalizer
{
    /**
     * Нормализует список хэштегов и при необходимости ставит тег тематики первым.
     *
     * @param  list<string>  $hashtags
     * @return list<string>
     */
    public function normalize(array $hashtags, ?string $categoryHashtag = null, int $limit = 7): array
    {
        $values = $categoryHashtag === null
            ? $hashtags
            : [$categoryHashtag, ...$hashtags];
        $result = [];
        $seen = [];

        foreach ($values as $hashtag) {
            $normalized = $this->normalizeOne($hashtag);
            if ($normalized === null) {
                continue;
            }

            $key = mb_strtolower($normalized);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $normalized;
            if (count($result) >= max(1, $limit)) {
                break;
            }
        }

        return $result;
    }

    /**
     * Очищает один тег и возвращает `null`, когда после удаления служебных символов
     * в нём не осталось букв, цифр или символов подчёркивания.
     */
    private function normalizeOne(string $hashtag): ?string
    {
        $value = html_entity_decode(trim($hashtag), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = ltrim($value, "# \t\n\r\0\x0B");
        $value = preg_replace('/[\s\-]+/u', '_', $value) ?? $value;
        $value = preg_replace('/[^\p{L}\p{N}_]/u', '', $value) ?? '';
        $value = trim($value, '_');

        return $value === '' ? null : '#'.$value;
    }
}
