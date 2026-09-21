<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Responses;

use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\Serializers\CommonSerializer;
use Rebit\Share\Infrastructure\Logger\RequestIdGenerator;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Exception\ValidationHttpException;

/** @extends AbstractResponse<ControllerJson> */
final class ApiJsonExceptionResponse extends AbstractResponse
{
    private const array ALLOWED_STATUSES = [400, 401, 403, 404, 409, 413, 422, 503];

    public function __construct(
        private readonly \Throwable $exception,
    ) {}

    protected function buildResponse(): ControllerJson
    {
        $status = $this->status();
        $code = $this->code($status);

        return (new ControllerJson(CommonSerializer::createDefault(), [
            'error' => [
                'code' => $code,
                'message' => $code,
            ],
            'meta' => [
                'requestId' => RequestIdGenerator::getRequestId(),
            ],
        ]))->setStatus($status);
    }

    private function status(): int
    {
        if ($this->exception instanceof ValidationHttpException) {
            return 422;
        }

        $status = $this->exception instanceof HttpException ? $this->exception->getCode() : 503;

        return in_array($status, self::ALLOWED_STATUSES, true) ? $status : 503;
    }

    private function code(int $status): string
    {
        if ($this->exception instanceof HttpException
            && 1 === preg_match('/^[A-Z_]+$/D', $this->exception->getMessage())) {
            return $this->exception->getMessage();
        }

        return 422 === $status ? 'VALIDATION_FAILED' : 'SERVICE_UNAVAILABLE';
    }
}
