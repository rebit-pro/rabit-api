<?php

declare(strict_types=1);

namespace Rebit\Share\Shared\Exception;

/**
 * Исключение, а так же базовый класс для исключений, которые умеет корректно сериализоваться в контроллерах.
 * Если нужно бросить исключение на фронт, то это оно.
 *
 * В наследниках достаточно переопределить константы, если нужен другой код ответа и/или дефолтное сообщение.
 */
class HttpException extends RebitException
{
    public const int HTTP_DEFAULT_EXCEPTION_CODE = 500;
    public const string DEFAULT_ERROR_MESSAGE = 'Server Error';

    /** @param array<string, mixed> $details безопасные данные для клиента, например актуальный расчёт при 409 */
    public function __construct(
        string $message = self::DEFAULT_ERROR_MESSAGE,
        int $code = self::HTTP_DEFAULT_EXCEPTION_CODE,
        ?\Exception $previous = null,
        private readonly array $details = [],
    ) {
        // чтобы дефолты брались с актуального класса.
        $message = (self::DEFAULT_ERROR_MESSAGE === $message)
            ? static::DEFAULT_ERROR_MESSAGE
            : $message;

        $code = (self::HTTP_DEFAULT_EXCEPTION_CODE === $code)
            ? static::HTTP_DEFAULT_EXCEPTION_CODE
            : $code;

        parent::__construct($message, $code, $previous);
    }

    /** @return array<string, mixed> */
    public function getDetails(): array
    {
        return $this->details;
    }
}
