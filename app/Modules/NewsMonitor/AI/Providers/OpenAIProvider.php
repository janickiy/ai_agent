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

final class OpenAIProvider extends AbstractRemoteAIProvider
{
    /**
     * Инициализирует интеграцию OpenAI и отклоняет небезопасный или неофициальный API URL.
     *
     * @param  array<string, mixed>  $config  Настройки авторизации, моделей и HTTP-клиента.
     */
    public function __construct(array $config)
    {
        parent::__construct($config);

        $this->assertSecureConfiguration('api.openai.com');
    }

    /**
     * Возвращает стабильный код провайдера, используемый в настройках, логах и результатах.
     */
    public function code(): string
    {
        return 'openai';
    }

    /**
     * Отправляет статью в OpenAI Responses API со строгой JSON Schema и преобразует
     * структурированный ответ в общий результат классификации.
     */
    public function analyzeArticle(ArticleAnalysisRequest $request): ArticleAnalysisResult
    {
        $categoryCodes = array_keys($request->categories);

        try {
            $response = $this->authorizedClient()
                ->post($this->endpoint('responses'), [
                    'model' => $this->model(),
                    'store' => false,
                    'instructions' => $this->analysisInstruction(),
                    'input' => json_encode(
                        $this->analysisPayload($request),
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
                    ),
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'article_analysis',
                            'schema' => $this->analysisSchema($categoryCodes),
                            'strict' => true,
                        ],
                    ],
                ])
                ->throw()
                ->json();
        } catch (AIProviderException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new AIProviderException('OpenAI request failed.', previous: $exception);
        }

        return $this->analysisResult(
            $this->decodeJson($this->responseText($response)),
            $categoryCodes,
            $this->model(),
        );
    }

    /**
     * Получает embeddings обеих статей одним запросом OpenAI и вычисляет их семантическое сходство.
     */
    public function compareArticles(ArticleComparisonRequest $request): ArticleComparisonResult
    {
        try {
            $response = $this->authorizedClient()
                ->post($this->endpoint('embeddings'), [
                    'model' => $this->embeddingModel(),
                    'input' => [$request->first, $request->second],
                    'encoding_format' => 'float',
                ])
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            throw new AIProviderException('OpenAI embedding request failed.', previous: $exception);
        }

        $first = $this->embeddingVector($response['data'][0]['embedding'] ?? null);
        $second = $this->embeddingVector($response['data'][1]['embedding'] ?? null);

        return new ArticleComparisonResult(
            $this->cosine($first, $second),
            $this->code(),
            $this->embeddingModel(),
        );
    }

    /**
     * Создаёт авторизованный HTTP-клиент OpenAI и добавляет необязательные заголовки
     * организации и проекта, если они заданы в настройках.
     */
    private function authorizedClient(): PendingRequest
    {
        $apiKey = trim((string) ($this->config['api_key'] ?? ''));
        if ($apiKey === '') {
            throw new AIProviderException('OpenAI API key is not configured.');
        }

        $headers = [];
        $organization = trim((string) ($this->config['organization'] ?? ''));
        $project = trim((string) ($this->config['project'] ?? ''));
        if ($organization !== '') {
            $headers['OpenAI-Organization'] = $organization;
        }
        if ($project !== '') {
            $headers['OpenAI-Project'] = $project;
        }

        return $this->client()->withToken($apiKey)->withHeaders($headers);
    }

    /**
     * Извлекает текст результата из структуры Responses API и отдельно обрабатывает
     * незавершённые ответы, отказ модели и отсутствие выходного текста.
     *
     * @param  array<string, mixed>  $response  Полный ответ OpenAI Responses API.
     */
    private function responseText(array $response): string
    {
        if (($response['status'] ?? null) !== 'completed') {
            $message = $response['error']['message'] ?? $response['incomplete_details']['reason'] ?? null;

            throw new AIProviderException(
                is_string($message) && $message !== '' ? 'OpenAI: '.$message : 'OpenAI response was not completed.',
            );
        }

        foreach ((array) ($response['output'] ?? []) as $output) {
            if (! is_array($output) || ($output['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ((array) ($output['content'] ?? []) as $content) {
                if (! is_array($content)) {
                    continue;
                }
                if (($content['type'] ?? null) === 'refusal') {
                    throw new AIProviderException('OpenAI refused to process the supplied content.');
                }
                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }

        throw new AIProviderException('OpenAI returned no output text.');
    }

    /**
     * Возвращает идентификатор модели OpenAI, выбранной для анализа статей.
     */
    private function model(): string
    {
        return (string) $this->config['model'];
    }

    /**
     * Возвращает идентификатор embedding-модели OpenAI для сравнения статей.
     */
    private function embeddingModel(): string
    {
        return (string) $this->config['embedding_model'];
    }
}
