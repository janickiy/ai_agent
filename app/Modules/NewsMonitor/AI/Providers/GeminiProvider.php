<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\AI\Providers;

use App\Modules\NewsMonitor\AI\DTO\ArticleAnalysisRequest;
use App\Modules\NewsMonitor\AI\DTO\ArticleAnalysisResult;
use App\Modules\NewsMonitor\AI\DTO\ArticleComparisonRequest;
use App\Modules\NewsMonitor\AI\DTO\ArticleComparisonResult;
use App\Modules\NewsMonitor\AI\Exceptions\AIProviderException;
use Illuminate\Http\Client\PendingRequest;
use InvalidArgumentException;
use Throwable;

/**
 * Интегрирует модуль мониторинга новостей с Google Gemini API.
 *
 * Провайдер классифицирует статьи через `generateContent` со строгой JSON Schema,
 * получает embeddings двух материалов одним пакетным запросом и возвращает результаты
 * в общих DTO модуля, не раскрывая API-ключ остальным компонентам приложения.
 */
final class GeminiProvider extends AbstractRemoteAIProvider
{
    public function __construct(array $config)
    {
        parent::__construct($config);

        $this->assertSecureConfiguration('generativelanguage.googleapis.com');
        $this->modelResource($this->model());
        $this->modelResource($this->embeddingModel());
    }

    /**
     * Возвращает стабильный код провайдера для настроек, логов и результатов обработки.
     */
    public function code(): string
    {
        return 'gemini';
    }

    /**
     * Отправляет статью в Gemini `generateContent`, требует ответ по JSON Schema
     * и преобразует структурированный результат в единый DTO классификации.
     */
    public function analyzeArticle(ArticleAnalysisRequest $request): ArticleAnalysisResult
    {
        $categoryCodes = array_keys($request->categories);

        try {
            $response = $this->authorizedClient()
                ->post($this->modelEndpoint($this->model(), 'generateContent'), [
                    'systemInstruction' => [
                        'parts' => [['text' => $this->analysisInstruction()]],
                    ],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [[
                            'text' => json_encode(
                                $this->analysisPayload($request),
                                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
                            ),
                        ]],
                    ]],
                    'generationConfig' => [
                        'maxOutputTokens' => 2000,
                        'responseFormat' => [
                            'text' => [
                                'mimeType' => 'application/json',
                                'schema' => $this->analysisSchema($categoryCodes),
                            ],
                        ],
                    ],
                ])
                ->throw()
                ->json();
        } catch (AIProviderException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new AIProviderException('Gemini request failed.', previous: $exception);
        }

        return $this->analysisResult(
            $this->decodeJson($this->responseText($response)),
            $categoryCodes,
            $this->model(),
        );
    }

    /**
     * Получает embeddings обеих статей одним запросом `batchEmbedContents`
     * и вычисляет их косинусное семантическое сходство.
     */
    public function compareArticles(ArticleComparisonRequest $request): ArticleComparisonResult
    {
        $model = $this->modelResource($this->embeddingModel());
        $embeddingRequest = static fn (string $text): array => [
            'model' => $model,
            'content' => [
                'parts' => [[
                    'text' => preg_replace('/\s+/u', ' ', trim($text)) ?? $text,
                ]],
            ],
            'embedContentConfig' => ['taskType' => 'SEMANTIC_SIMILARITY'],
        ];

        try {
            $response = $this->authorizedClient()
                ->post($this->modelEndpoint($this->embeddingModel(), 'batchEmbedContents'), [
                    'requests' => [
                        $embeddingRequest($request->first),
                        $embeddingRequest($request->second),
                    ],
                ])
                ->throw()
                ->json();
        } catch (AIProviderException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new AIProviderException('Gemini embedding request failed.', previous: $exception);
        }

        $first = $this->embeddingVector($response['embeddings'][0]['values'] ?? null);
        $second = $this->embeddingVector($response['embeddings'][1]['values'] ?? null);

        return new ArticleComparisonResult(
            $this->cosine($first, $second),
            $this->code(),
            $this->embeddingModel(),
        );
    }

    /**
     * Создаёт HTTP-клиент Gemini с API-ключом в официальном заголовке `x-goog-api-key`.
     */
    private function authorizedClient(): PendingRequest
    {
        $apiKey = trim((string) ($this->config['api_key'] ?? ''));
        if ($apiKey === '') {
            throw new AIProviderException('Gemini API key is not configured.');
        }

        return $this->client()->withHeaders(['x-goog-api-key' => $apiKey]);
    }

    /**
     * Извлекает текст из первой кандидатуры Gemini и сообщает о блокировке,
     * незавершённой генерации либо отсутствии ожидаемого содержимого.
     *
     * @param  array<string, mixed>  $response
     */
    private function responseText(array $response): string
    {
        $blockReason = $response['promptFeedback']['blockReason'] ?? null;
        if (is_string($blockReason) && $blockReason !== '') {
            throw new AIProviderException('Gemini blocked the supplied content: '.$blockReason.'.');
        }

        $candidate = $response['candidates'][0] ?? null;
        if (! is_array($candidate)) {
            throw new AIProviderException('Gemini returned no candidates.');
        }

        $finishReason = (string) ($candidate['finishReason'] ?? '');
        if ($finishReason !== '' && $finishReason !== 'STOP') {
            throw new AIProviderException('Gemini response was not completed: '.$finishReason.'.');
        }

        $text = '';
        foreach ((array) data_get($candidate, 'content.parts', []) as $part) {
            if (is_array($part) && is_string($part['text'] ?? null)) {
                $text .= $part['text'];
            }
        }

        if ($text === '') {
            throw new AIProviderException('Gemini returned no output text.');
        }

        return $text;
    }

    /**
     * Собирает URL метода для конкретной модели после проверки безопасного формата её идентификатора.
     */
    private function modelEndpoint(string $model, string $method): string
    {
        return $this->endpoint($this->modelResource($model).':'.$method);
    }

    /**
     * Нормализует идентификатор модели в ресурс `models/{id}` и не допускает
     * подмены пути API через редактируемое значение в настройках.
     */
    private function modelResource(string $model): string
    {
        $model = preg_replace('#^models/#', '', trim($model)) ?? '';
        if ($model === '' || preg_match('/^[a-zA-Z0-9._-]+$/', $model) !== 1) {
            throw new InvalidArgumentException('Gemini model identifier is invalid.');
        }

        return 'models/'.$model;
    }

    /**
     * Возвращает идентификатор генеративной модели Gemini для анализа статей.
     */
    private function model(): string
    {
        return (string) $this->config['model'];
    }

    /**
     * Возвращает идентификатор embedding-модели Gemini для сравнения материалов.
     */
    private function embeddingModel(): string
    {
        return (string) $this->config['embedding_model'];
    }
}
