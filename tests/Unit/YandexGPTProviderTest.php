<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\NewsMonitor\AI\DTO\ArticleAnalysisRequest;
use App\Modules\NewsMonitor\AI\DTO\ArticleComparisonRequest;
use App\Modules\NewsMonitor\AI\Providers\YandexGPTProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

final class YandexGPTProviderTest extends TestCase
{
    public function test_it_analyzes_an_article_with_structured_output_and_api_key(): void
    {
        Http::fake([
            'https://ai.api.cloud.yandex.net/v1/chat/completions' => Http::response([
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => [
                        'content' => json_encode($this->analysis(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ]),
        ]);

        $result = $this->provider()->analyzeArticle($this->request());

        self::assertSame('construction', $result->categoryCode);
        self::assertSame('yandexgpt', $result->provider);
        self::assertSame('gpt://test-folder/yandexgpt/latest', $result->model);
        Http::assertSent(static function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://ai.api.cloud.yandex.net/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Api-Key test-yandex-key')
                && $request->hasHeader('OpenAI-Project', 'test-folder')
                && $payload['model'] === 'gpt://test-folder/yandexgpt/latest'
                && data_get($payload, 'response_format.type') === 'json_schema'
                && data_get($payload, 'response_format.json_schema.name') === 'article_analysis'
                && data_get($payload, 'response_format.json_schema.schema.additionalProperties') === false;
        });
    }

    public function test_it_uses_iam_token_when_api_key_is_not_configured(): void
    {
        Http::fake([
            'https://ai.api.cloud.yandex.net/v1/chat/completions' => Http::response([
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => [
                        'content' => json_encode($this->analysis(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ]),
        ]);

        (new YandexGPTProvider([
            ...$this->config(),
            'api_key' => '',
            'iam_token' => 'test-iam-token',
        ]))->analyzeArticle($this->request());

        Http::assertSent(static fn (Request $request): bool => $request->hasHeader(
            'Authorization',
            'Bearer test-iam-token',
        ));
    }

    public function test_it_compares_articles_with_yandex_embeddings(): void
    {
        Http::fakeSequence()
            ->push(['data' => [['embedding' => [1.0, 1.0, 0.0]]]], 200)
            ->push(['data' => [['embedding' => [1.0, 1.0, 0.0]]]], 200);

        $result = $this->provider()->compareArticles(new ArticleComparisonRequest(
            "Первый\nстроительный материал",
            'Второй строительный материал',
        ));

        self::assertEqualsWithDelta(1.0, $result->similarity, 0.000001);
        self::assertSame('yandexgpt', $result->provider);
        self::assertSame('emb://test-folder/text-search-doc/latest', $result->model);
        Http::assertSentCount(2);
        Http::assertSent(static fn (Request $request): bool => $request->url() === 'https://ai.api.cloud.yandex.net/v1/embeddings'
            && data_get($request->data(), 'model') === 'emb://test-folder/text-search-doc/latest'
            && data_get($request->data(), 'encoding_format') === 'float');
    }

    public function test_it_rejects_untrusted_endpoint(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new YandexGPTProvider([...$this->config(), 'api_url' => 'https://attacker.example.test/v1']);
    }

    public function test_it_rejects_disabled_tls_verification(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new YandexGPTProvider([...$this->config(), 'verify_ssl' => false]);
    }

    private function provider(): YandexGPTProvider
    {
        return new YandexGPTProvider($this->config());
    }

    private function request(): ArticleAnalysisRequest
    {
        return new ArticleAnalysisRequest(
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
        );
    }

    /** @return array<string, mixed> */
    private function analysis(): array
    {
        return [
            'category_code' => 'construction',
            'category_confidence' => 0.96,
            'is_advertising' => false,
            'ad_confidence' => 0.03,
            'hashtags' => ['#Строительство'],
            'entities' => ['Москва'],
            'reason' => 'Статья посвящена строительству объекта.',
        ];
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        return [
            'api_url' => 'https://ai.api.cloud.yandex.net/v1',
            'folder_id' => 'test-folder',
            'api_key' => 'test-yandex-key',
            'iam_token' => '',
            'model' => 'yandexgpt/latest',
            'embedding_model' => 'text-search-doc/latest',
            'timeout' => 5,
            'connect_timeout' => 1,
            'max_attempts' => 1,
            'verify_ssl' => true,
        ];
    }
}
