<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\NewsMonitor\Services\HashtagNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет единый формат хэштегов, сохраняемых после ответа AI-провайдера.
 */
final class HashtagNormalizerTest extends TestCase
{
    /**
     * Убеждается, что скобки и пунктуация удаляются, пробелы становятся
     * подчёркиваниями, а дубли не попадают в готовую публикацию.
     */
    public function test_it_removes_braces_and_deduplicates_hashtags(): void
    {
        $normalizer = new HashtagNormalizer;

        self::assertSame([
            '#Новостройки',
            '#Дноуглубление',
            '#Северный_морской_путь',
        ], $normalizer->normalize([
            '#{Новостройки}',
            '#Дноуглубление}',
            ' {#новостройки} ',
            'Северный морской путь',
            '{}',
        ]));
    }

    /**
     * Проверяет, что обязательный хэштег тематики располагается первым и также очищается.
     */
    public function test_it_puts_normalized_category_hashtag_first(): void
    {
        $normalizer = new HashtagNormalizer;

        self::assertSame(
            ['#Строительство', '#Москва'],
            $normalizer->normalize(['#Москва', '#Строительство'], '#{Строительство}'),
        );
    }
}
