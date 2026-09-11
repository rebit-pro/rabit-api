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

    public function __construct(
        string $message = self::DEFAULT_ERROR_MESSAGE,
        int $code = self::HTTP_DEFAULT_EXCEPTION_CODE,
        ?\Exception $previous = null,
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
}
