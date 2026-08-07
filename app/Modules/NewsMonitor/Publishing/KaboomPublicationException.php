<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Publishing;

use RuntimeException;
use Throwable;

/**
 * Описывает ошибку отправки новости в Kaboom и сообщает очереди, допустим ли повтор.
 */
final class KaboomPublicationException extends RuntimeException
{
    /**
     * Создаёт исключение с безопасным сообщением без X-API-Key.
     */
    public function __construct(
        string $message,
        private readonly bool $retryable,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    /**
     * Возвращает признак временной ошибки, для которой очередь должна выполнить повтор.
     */
    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
