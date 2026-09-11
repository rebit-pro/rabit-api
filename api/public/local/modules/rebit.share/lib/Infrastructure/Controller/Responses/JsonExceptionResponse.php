<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Responses;

use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\Serializers\CommonSerializer;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Exception\ValidationHttpException;
use Rebit\Share\Domain\File\Exception\InvalidFileException;

/**
 * Класс для формирования Json-ответа API, если произошло исключение.
 *
 * @extends AbstractResponse<\Rebit\Share\Infrastructure\Bitrix\ControllerJson>
 */
final class JsonExceptionResponse extends AbstractResponse
{
    public function __construct(
        private readonly \Throwable $exception,
        private readonly bool $debug = false,
    ) {}

    protected function buildResponse(): ControllerJson
    {
        $code = match (true) {
            $this->exception instanceof InvalidFileException => 400,
            $this->exception instanceof HttpException => $this->exception->getCode(),
            default => 500,
        };

        $error = [
            'message' => match (true) {
                $this->exception instanceof InvalidFileException => 'Некорректный файл или параметры загрузки.',
                $this->exception instanceof HttpException, $this->debug => $this->exception->getMessage(),
                default => 'Server Error',
            },
        ];

        $content = [
            'data' => [],
            'error' => $error,
        ];

        if ($this->debug && !$this->exception instanceof InvalidFileException
            && !$this->exception instanceof ValidationHttpException) {
            $content['error']['debug'] = [
                'type' => get_class($this->exception),
                'file' => $this->exception->getFile(),
                'line' => $this->exception->getLine(),
                'trace' => explode("\n", $this->exception->getTraceAsString()),
            ];
        }

        $json = new ControllerJson(CommonSerializer::createDefault(), $content, JSON_UNESCAPED_UNICODE);
        $json->setStatus($code);

        return $json;
    }
}
