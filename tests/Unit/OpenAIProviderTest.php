<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\NewsMonitor\AI\DTO\ArticleAnalysisRequest;
use App\Modules\NewsMonitor\AI\DTO\ArticleComparisonRequest;
use App\Modules\NewsMonitor\AI\Providers\OpenAIProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

final class OpenAIProviderTest extends TestCase
{
    public function test_it_analyzes_an_article_with_responses_structured_output(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'status' => 'completed',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode($this->analysis(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    ]],
                ]],
            ]),
        ]);

        $result = $this->provider()->analyzeArticle($this->request());

        self::assertSame('construction', $result->categoryCode);
        self::assertSame('openai', $result->provider);
        self::assertSame('gpt-5.6', $result->model);
        Http::assertSent(static function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request->hasHeader('Authorization', 'Bearer test-openai-key')
                && $request->hasHeader('OpenAI-Organization', 'test-organization')
                && $request->hasHeader('OpenAI-Project', 'test-project')
                && $payload['store'] === false
                && data_get($payload, 'text.format.type') === 'json_schema'
                && data_get($payload, 'text.format.strict') === true
                && data_get($payload, 'text.format.schema.additionalProperties') === false;
        });
    }

    public function test_it_compares_articles_with_openai_embeddings(): void
    {
        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['index' => 0, 'embedding' => [1.0, 0.0, 1.0]],
                    ['index' => 1, 'embedding' => [1.0, 0.0, 1.0]],
                ],
            ]),
        ]);

        $result = $this->provider()->compareArticles(new ArticleComparisonRequest(
            'Первый строительный материал',
            'Второй строительный материал',
        ));

        self::assertEqualsWithDelta(1.0, $result->similarity, 0.000001);
        self::assertSame('openai', $result->provider);
        self::assertSame('text-embedding-3-small', $result->model);
        Http::assertSent(static fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/embeddings'
            && data_get($request->data(), 'input.0') === 'Первый строительный материал'
            && data_get($request->data(), 'input.1') === 'Второй строительный материал'
            && data_get($request->data(), 'encoding_format') === 'float');
    }

    public function test_it_rejects_untrusted_endpoint_and_disabled_tls(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OpenAIProvider([...$this->config(), 'api_url' => 'https://attacker.example.test/v1']);
    }

    public function test_it_rejects_disabled_tls_verification(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OpenAIProvider([...$this->config(), 'verify_ssl' => false]);
    }

    private function provider(): OpenAIProvider
    {
        return new OpenAIProvider($this->config());
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
            'category_confidence' => 0.98,
            'is_advertising' => false,
            'ad_confidence' => 0.02,
            'hashtags' => ['#Строительство'],
            'entities' => ['Москва'],
            'reason' => 'Статья посвящена строительству объекта.',
        ];
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        return [
            'api_url' => 'https://api.openai.com/v1',
            'api_key' => 'test-openai-key',
            'model' => 'gpt-5.6',
            'embedding_model' => 'text-embedding-3-small',
            'organization' => 'test-organization',
            'project' => 'test-project',
            'timeout' => 5,
            'connect_timeout' => 1,
            'max_attempts' => 1,
            'verify_ssl' => true,
        ];
    }
}
