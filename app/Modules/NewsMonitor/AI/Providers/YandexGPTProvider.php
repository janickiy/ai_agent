<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\AI\Providers;

use App\Modules\NewsMonitor\AI\DTO\ArticleAnalysisRequest;
use App\Modules\NewsMonitor\AI\DTO\ArticleAnalysisResult;
use App\Modules\NewsMonitor\AI\DTO\ArticleComparisonRequest;
use App\Modules\NewsMonitor\AI\DTO\ArticleComparisonResult;
use App\Modules\NewsMonitor\AI\Exceptions\AIProviderException;
use Illuminate\Http\Client\PendingRequest;
use Throwable;

final class YandexGPTProvider extends AbstractRemoteAIProvider
{
    public function __construct(array $config)
    {
        parent::__construct($config);

        $this->assertSecureConfiguration('ai.api.cloud.yandex.net');
    }

    /**
     * Возвращает стабильный код провайдера, используемый в настройках, логах и результатах.
     */
    public function code(): string
    {
        return 'yandexgpt';
    }

    /**
     * Передаёт статью в YandexGPT Chat Completions со схемой структурированного ответа
     * и преобразует результат в общий DTO классификации.
     *
     * @param ArticleAnalysisRequest $request
     * @return ArticleAnalysisResult
     */
    public function analyzeArticle(ArticleAnalysisRequest $request): ArticleAnalysisResult
    {
        $categoryCodes = array_keys($request->categories);

        try {
            $response = $this->authorizedClient()
                ->post($this->endpoint('chat/completions'), [
                    'model' => $this->modelUri(),
                    'messages' => [
                        ['role' => 'system', 'content' => $this->analysisInstruction()],
                        [
                            'role' => 'user',
                            'content' => json_encode(
                                $this->analysisPayload($request),
                                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
                            ),
                        ],
                    ],
                    'max_tokens' => 2000,
                    'temperature' => 0.1,
                    'stream' => false,
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'article_analysis',
                            'schema' => $this->analysisSchema($categoryCodes),
                        ],
                    ],
                ])
                ->throw()
                ->json();
        } catch (AIProviderException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new AIProviderException('YandexGPT request failed.', previous: $exception);
        }

        $finishReason = (string) ($response['choices'][0]['finish_reason'] ?? '');
        if ($finishReason === 'content_filter') {
            throw new AIProviderException('YandexGPT blocked the supplied content.');
        }

        $content = $response['choices'][0]['message']['content'] ?? null;
        if (! is_string($content) || $content === '') {
            throw new AIProviderException('YandexGPT returned no output text.');
        }

        return $this->analysisResult(
            $this->decodeJson($content),
            $categoryCodes,
            $this->modelUri(),
        );
    }

    /**
     * Строит embedding для каждой статьи и вычисляет косинусное семантическое сходство.
     *
     * @param ArticleComparisonRequest $request
     * @return ArticleComparisonResult
     */
    public function compareArticles(ArticleComparisonRequest $request): ArticleComparisonResult
    {
        $first = $this->createEmbedding($request->first);
        $second = $this->createEmbedding($request->second);

        return new ArticleComparisonResult(
            $this->cosine($first, $second),
            $this->code(),
            $this->embeddingModelUri(),
        );
    }

    /**
     * Запрашивает у Yandex AI Studio embedding одного текста и проверяет формат вектора.
     *
     * @param string $text
     * @return array
     */
    private function createEmbedding(string $text): array
    {
        try {
            $response = $this->authorizedClient()
                ->post($this->endpoint('embeddings'), [
                    'model' => $this->embeddingModelUri(),
                    'input' => preg_replace('/\s+/u', ' ', trim($text)) ?? $text,
                    'encoding_format' => 'float',
                ])
                ->throw()
                ->json();
        } catch (AIProviderException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new AIProviderException('YandexGPT embedding request failed.', previous: $exception);
        }

        return $this->embeddingVector($response['data'][0]['embedding'] ?? null);
    }

    /**
     * Создаёт клиент Yandex AI Studio с Folder ID и выбирает подходящий способ
     * авторизации: API-ключ либо IAM-токен.
     */
    private function authorizedClient(): PendingRequest
    {
        $folderId = $this->folderId();
        $apiKey = trim((string) ($this->config['api_key'] ?? ''));
        $iamToken = trim((string) ($this->config['iam_token'] ?? ''));
        if ($apiKey === '' && $iamToken === '') {
            throw new AIProviderException('YandexGPT credentials are not configured.');
        }

        $client = $this->client()->withHeaders(['OpenAI-Project' => $folderId]);

        return $apiKey !== ''
            ? $client->withHeaders(['Authorization' => 'Api-Key '.$apiKey])
            : $client->withToken($iamToken);
    }

    /**
     * Возвращает полный URI генеративной модели с учётом Folder ID.
     */
    private function modelUri(): string
    {
        return $this->modelUriFor((string) $this->config['model'], 'gpt');
    }

    /**
     * Возвращает полный URI embedding-модели с учётом Folder ID.
     */
    private function embeddingModelUri(): string
    {
        return $this->modelUriFor((string) $this->config['embedding_model'], 'emb');
    }

    /**
     * Дополняет короткое имя модели схемой и Folder ID, сохраняя уже готовый URI без изменений.
     */
    private function modelUriFor(string $model, string $scheme): string
    {
        $model = trim($model);
        if (str_contains($model, '://')) {
            return $model;
        }

        return $scheme.'://'.$this->folderId().'/'.ltrim($model, '/');
    }

    /**
     * Возвращает обязательный идентификатор каталога Yandex Cloud или сообщает об ошибке настройки.
     */
    private function folderId(): string
    {
        $folderId = trim((string) ($this->config['folder_id'] ?? ''));
        if ($folderId === '') {
            throw new AIProviderException('YandexGPT Folder ID is not configured.');
        }

        return $folderId;
    }
}
