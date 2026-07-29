<?php

declare(strict_types=1);

namespace App\NewsMonitor\Services;

final class ContentNormalizer
{
    public function copiedField(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]/u', '', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    public function body(string $value): string
    {
        return $this->copiedField($value);
    }
}
