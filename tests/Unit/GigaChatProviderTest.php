<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\NewsMonitor\AI\DTO\ArticleComparisonRequest;
use App\NewsMonitor\AI\Providers\GigaChatProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class GigaChatProviderTest extends TestCase
{
    public function test_it_uses_local_comparison_when_embeddings_are_not_in_the_tariff(): void
    {
        Http::fakeSequence()
            ->push(['access_token' => 'test-token'], 200)
            ->push(['status' => 402, 'message' => 'Payment Required'], 402);

        $provider = new GigaChatProvider([
            'auth_url' => 'https://auth.example/oauth',
            'api_url' => 'https://api.example/v1',
            'auth_key' => 'test-key',
            'client_id' => null,
            'client_secret' => null,
            'scope' => 'GIGACHAT_API_PERS',
            'model' => 'GigaChat-2-Max',
            'embedding_model' => 'EmbeddingsGigaR',
            'embedding_fallback' => true,
            'timeout' => 5,
            'connect_timeout' => 1,
            'max_attempts' => 1,
            'verify_ssl' => true,
        ]);

        $result = $provider->compareArticles(new ArticleComparisonRequest(
            'Завершено строительство нового моста',
            'Завершено строительство нового моста',
        ));

        self::assertSame('rules', $result->provider);
        self::assertSame(1.0, $result->similarity);
        Http::assertSentCount(2);
    }
}
