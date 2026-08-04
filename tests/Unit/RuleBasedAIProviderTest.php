<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\NewsMonitor\AI\DTO\ArticleAnalysisRequest;
use App\Modules\NewsMonitor\AI\Providers\RuleBasedAIProvider;
use Tests\TestCase;

final class RuleBasedAIProviderTest extends TestCase
{
    public function test_it_classifies_a_transport_construction_article(): void
    {
        $provider = new RuleBasedAIProvider;
        $config = require dirname(__DIR__, 2).'/config/news.php';
        $result = $provider->analyzeArticle(new ArticleAnalysisRequest(
            'Началось строительство нового моста',
            'Магистраль соединит два района города.',
            'Подрядчик приступил к строительству транспортной инфраструктуры.',
            $config['categories'],
        ));

        self::assertSame('construction', $result->categoryCode);
        self::assertFalse($result->isAdvertising);
        self::assertContains('#Строительство', $result->hashtags);
    }

    public function test_it_marks_explicit_advertising(): void
    {
        $provider = new RuleBasedAIProvider;
        $config = require dirname(__DIR__, 2).'/config/news.php';
        $result = $provider->analyzeArticle(new ArticleAnalysisRequest(
            'Строительство дома',
            'На правах рекламы. Купите квартиру.',
            '',
            $config['categories'],
        ));

        self::assertTrue($result->isAdvertising);
        self::assertGreaterThanOrEqual(0.9, $result->adConfidence);
    }
}
