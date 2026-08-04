<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\NewsMonitor\AI\DTO\ArticleAnalysisRequest;
use App\Modules\NewsMonitor\AI\DTO\ArticleComparisonRequest;
use App\Modules\NewsMonitor\AI\Providers\GigaChatProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

final class GigaChatProviderTest extends TestCase
{
    public function test_it_requests_structured_article_analysis_with_json_schema(): void
    {
        Cache::flush();
        Http::fakeSequence()
            ->push(['access_token' => 'test-token'], 200)
            ->push([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'category_code' => 'construction',
                            'category_confidence' => 0.98,
                            'is_advertising' => false,
                            'ad_confidence' => 0.02,
                            'hashtags' => ['#Строительство'],
                            'entities' => ['Москва'],
                            'reason' => 'Статья посвящена строительству объекта.',
                        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ], 200);

        $provider = $this->provider();
        $result = $provider->analyzeArticle(new ArticleAnalysisRequest(
            'Завершено строительство моста',
            'Новый мост открыли в Москве.',
            'Строительство объекта завершено в срок.',
            [
                'construction' => [
                    'name' => 'Строительство',
                    'hashtag' => '#Строительство',
                    'keywords' => ['строительство'],
                ],
            ],
        ));

        self::assertSame('construction', $result->categoryCode);
        self::assertSame('gigachat', $result->provider);
        Http::assertSent(static function (Request $request): bool {
            if (! str_ends_with($request->url(), '/chat/completions')) {
                return false;
            }

            $payload = $request->data();

            return data_get($payload, 'response_format.type') === 'json_schema'
                && data_get($payload, 'response_format.strict') === true
                && data_get($payload, 'response_format.schema.type') === 'object'
                && in_array(
                    'category_code',
                    data_get($payload, 'response_format.schema.required', []),
                    true,
                );
        });
    }

    public function test_it_retries_blocked_article_without_full_body(): void
    {
        Cache::flush();
        Http::fakeSequence()
            ->push(['access_token' => 'test-token'], 200)
            ->push([
                'choices' => [[
                    'message' => ['content' => 'Ответ ограничен.'],
                    'finish_reason' => 'blacklist',
                ]],
            ], 200)
            ->push([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'category_code' => 'construction',
                            'category_confidence' => 0.91,
                            'is_advertising' => false,
                            'ad_confidence' => 0.01,
                            'hashtags' => ['#Строительство'],
                            'entities' => [],
                            'reason' => 'Строительная тематика.',
                        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    ],
                    'finish_reason' => 'stop',
                ]],
            ], 200);

        $provider = $this->provider();
        $result = $provider->analyzeArticle(new ArticleAnalysisRequest(
            'Завершено строительство объекта',
            'Объект введён в эксплуатацию.',
            'Полный текст, который вызвал ограничение модели.',
            [
                'construction' => [
                    'name' => 'Строительство',
                    'hashtag' => '#Строительство',
                    'keywords' => ['строительство'],
                ],
            ],
        ));

        self::assertSame('construction', $result->categoryCode);
        self::assertSame('gigachat', $result->provider);

        $chatRequests = collect(Http::recorded())
            ->map(static fn (array $record): Request => $record[0])
            ->filter(static fn (Request $request): bool => str_ends_with($request->url(), '/chat/completions'))
            ->values();

        self::assertCount(2, $chatRequests);
        $fallbackMessage = data_get($chatRequests[1]->data(), 'messages.1.content');
        $fallbackPayload = json_decode((string) $fallbackMessage, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('', $fallbackPayload['body']);
    }

    public function test_it_retries_invalid_json_without_full_body(): void
    {
        Cache::flush();
        Http::fakeSequence()
            ->push(['access_token' => 'test-token'], 200)
            ->push([
                'choices' => [[
                    'message' => ['content' => 'Неструктурированный ответ модели.'],
                    'finish_reason' => 'stop',
                ]],
            ], 200)
            ->push([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'category_code' => 'construction',
                            'category_confidence' => 0.88,
                            'is_advertising' => false,
                            'ad_confidence' => 0.03,
                            'hashtags' => ['#Строительство'],
                            'entities' => [],
                            'reason' => 'Строительная тематика.',
                        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    ],
                    'finish_reason' => 'stop',
                ]],
            ], 200);

        $result = $this->provider()->analyzeArticle(new ArticleAnalysisRequest(
            'Строительство объекта завершено',
            'Объект ввели в эксплуатацию.',
            'Полный текст материала.',
            [
                'construction' => [
                    'name' => 'Строительство',
                    'hashtag' => '#Строительство',
                    'keywords' => ['строительство'],
                ],
            ],
        ));

        self::assertSame('construction', $result->categoryCode);
        self::assertSame('gigachat', $result->provider);
        Http::assertSentCount(3);
    }

    public function test_it_uses_local_comparison_when_embeddings_are_not_in_the_tariff(): void
    {
        Cache::flush();
        Http::fakeSequence()
            ->push(['access_token' => 'test-token'], 200)
            ->push(['status' => 402, 'message' => 'Payment Required'], 402);

        $provider = $this->provider();

        $result = $provider->compareArticles(new ArticleComparisonRequest(
            'Завершено строительство нового моста',
            'Завершено строительство нового моста',
        ));

        self::assertSame('rules', $result->provider);
        self::assertSame(1.0, $result->similarity);
        Http::assertSentCount(2);
    }

    public function test_it_rejects_untrusted_api_endpoints_before_sending_credentials(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        new GigaChatProvider([
            ...$this->config(),
            'auth_url' => 'https://attacker.example.test/oauth',
        ]);
    }

    public function test_it_rejects_disabled_tls_verification(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GigaChatProvider([
            ...$this->config(),
            'verify_ssl' => false,
        ]);
    }

    private function provider(): GigaChatProvider
    {
        return new GigaChatProvider($this->config());
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        return [
            'auth_url' => 'https://ngw.devices.sberbank.ru:9443/api/v2/oauth',
            'api_url' => 'https://api.giga.chat/v1',
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
        ];
    }
}
