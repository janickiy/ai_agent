<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Publishing;

use App\DTO\Publishing\KaboomPublicationData;
use App\Modules\NewsMonitor\Services\KaboomSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Отправляет подготовленные новости в фиксированный API Инстройграма Kaboom.
 *
 * Клиент использует multipart/form-data, запрещает редиректы с секретным заголовком,
 * принимает только ответы 200/201 и классифицирует ошибки для повторов очереди.
 */
final readonly class KaboomClient
{
    private const TIMEOUT_SECONDS = 120;

    private const CONNECT_TIMEOUT_SECONDS = 10;

    /**
     * Получает безопасный доступ к зашифрованному X-API-Key.
     */
    public function __construct(private KaboomSettings $settings) {}

    /**
     * Публикует новость и возвращает проверенный результат внешнего API.
     *
     * @throws KaboomPublicationException
     */
    public function publish(KaboomPublicationData $publication): KaboomPublicationResult
    {
        try {
            $apiKey = $this->settings->apiKey();
        } catch (RuntimeException $exception) {
            throw new KaboomPublicationException($exception->getMessage(), false, $exception);
        }
        if ($apiKey === '') {
            throw new KaboomPublicationException(
                'X-API-Key Kaboom не настроен в административной панели.',
                false,
            );
        }

        try {
            $response = Http::acceptJson()
                ->asMultipart()
                ->withHeaders(['X-API-Key' => $apiKey])
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::TIMEOUT_SECONDS)
                ->withOptions(['allow_redirects' => false])
                ->post(KaboomSettings::ENDPOINT, $publication->toArray());
        } catch (ConnectionException $exception) {
            throw new KaboomPublicationException(
                'Не удалось подключиться к API Kaboom: '.$exception->getMessage(),
                true,
                $exception,
            );
        }

        if (! in_array($response->status(), [200, 201], true)) {
            throw $this->responseException($response);
        }

        $body = $response->json();
        $externalId = is_array($body) ? ($body['id'] ?? null) : null;
        $uid = is_array($body) ? ($body['uid'] ?? null) : null;
        $created = is_array($body) ? ($body['created'] ?? null) : null;
        $message = is_array($body) ? ($body['message'] ?? '') : '';

        if (
            ! is_int($externalId)
            || ! is_string($uid)
            || $uid !== $publication->uid
            || ! is_bool($created)
            || ! is_string($message)
            || trim($message) === ''
            || ($response->status() === 201 && ! $created)
            || ($response->status() === 200 && $created)
        ) {
            throw new KaboomPublicationException(
                'API Kaboom вернул неполный или несогласованный успешный ответ.',
                true,
            );
        }

        return new KaboomPublicationResult(
            statusCode: $response->status(),
            externalId: $externalId,
            uid: $uid,
            created: $created,
            message: $message,
        );
    }

    /**
     * Преобразует неуспешный HTTP-ответ в безопасное исключение с политикой повтора.
     */
    private function responseException(Response $response): KaboomPublicationException
    {
        $status = $response->status();
        $retryable = $status === 409 || $status === 429 || $status >= 500;
        $body = trim(Str::limit($response->body(), 1000));
        $message = "API Kaboom вернул HTTP {$status}";

        if ($body !== '') {
            $message .= ': '.$body;
        }

        return new KaboomPublicationException($message, $retryable);
    }
}
