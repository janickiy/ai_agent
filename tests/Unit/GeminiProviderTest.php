<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\NewsMonitor\AI\DTO\ArticleAnalysisRequest;
use App\Modules\NewsMonitor\AI\DTO\ArticleComparisonRequest;
use App\Modules\NewsMonitor\AI\Exceptions\AIProviderException;
use App\Modules\NewsMonitor\AI\Providers\GeminiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

final class GeminiProviderTest extends TestCase
{
    public function test_it_analyzes_an_article_with_structured_output(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent' => Http::response([
                'candidates' => [[
                    'finishReason' => 'STOP',
                    'content' => [
                        'parts' => [[
                            'text' => json_encode(
                                $this->analysis(),
                                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                            ),
                        ]],
                    ],
                ]],
            ]),
        ]);

        $result = $this->provider()->analyzeArticle($this->request());

        self::assertSame('construction', $result->categoryCode);
        self::assertSame('gemini', $result->provider);
        self::assertSame('gemini-3.6-flash', $result->model);
        Http::assertSent(static function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent'
                && $request->hasHeader('x-goog-api-key', 'test-gemini-key')
                && data_get($payload, 'systemInstruction.parts.0.text') !== null
                && data_get($payload, 'contents.0.role') === 'user'
                && data_get($payload, 'generationConfig.responseFormat.text.mimeType') === 'application/json'
                && data_get($payload, 'generationConfig.responseFormat.text.schema.additionalProperties') === false;
        });
    }

    public function test_it_compares_articles_with_batch_embeddings(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-2:batchEmbedContents' => Http::response([
                'embeddings' => [
                    ['values' => [1.0, 1.0, 0.0]],
                    ['values' => [1.0, 1.0, 0.0]],
                ],
            ]),
        ]);

        $result = $this->provider()->compareArticles(new ArticleComparisonRequest(
            "Первый\nстроительный материал",
            'Второй строительный материал',
        ));

        self::assertEqualsWithDelta(1.0, $result->similarity, 0.000001);
        self::assertSame('gemini', $result->provider);
        self::assertSame('gemini-embedding-2', $result->model);
        Http::assertSent(static function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-2:batchEmbedContents'
                && data_get($payload, 'requests.0.model') === 'models/gemini-embedding-2'
                && data_get($payload, 'requests.1.model') === 'models/gemini-embedding-2'
                && data_get($payload, 'requests.0.content.parts.0.text') === 'Первый строительный материал'
                && data_get($payload, 'requests.0.embedContentConfig.taskType') === 'SEMANTIC_SIMILARITY';
        });
    }

    public function test_it_reports_content_blocking(): void
    {
        Http::fake([
            '*' => Http::response([
                'promptFeedback' => ['blockReason' => 'SAFETY'],
            ]),
        ]);

        $this->expectException(AIProviderException::class);
        $this->expectExceptionMessage('Gemini blocked the supplied content');

        $this->provider()->analyzeArticle($this->request());
    }

    public function test_it_rejects_untrusted_endpoint(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new GeminiProvider([...$this->config(), 'api_url' => 'https://attacker.example.test/v1beta']);
    }

    public function test_it_rejects_disabled_tls_verification(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new GeminiProvider([...$this->config(), 'verify_ssl' => false]);
    }

    public function test_it_rejects_model_identifiers_that_can_change_the_api_path(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Gemini model identifier is invalid.');

        (new GeminiProvider([...$this->config(), 'model' => '../models/other']))
            ->analyzeArticle($this->request());
    }

    private function provider(): GeminiProvider
    {
        return new GeminiProvider($this->config());
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
            'category_confidence' => 0.97,
            'is_advertising' => false,
            'ad_confidence' => 0.01,
            'hashtags' => ['#Строительство'],
            'entities' => ['Москва'],
            'reason' => 'Статья посвящена строительству объекта.',
        ];
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        return [
            'api_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'api_key' => 'test-gemini-key',
            'model' => 'gemini-3.6-flash',
            'embedding_model' => 'gemini-embedding-2',
            'timeout' => 5,
            'connect_timeout' => 1,
            'max_attempts' => 1,
            'verify_ssl' => true,
        ];
    }
}
