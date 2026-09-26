<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller;

use Bitrix\Main\HttpResponse;
use Bitrix\Main\Response;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\Responses\ApiJsonExceptionResponse;
use Rebit\Share\Application\Contract\File\Dto\PreviewContentOutputDto;
use Rebit\Share\Application\Contract\File\Dto\ProtectedFileOutputDto;
use Rebit\Share\Infrastructure\Controller\Responses\PreviewResponse;
use Rebit\Share\Infrastructure\Controller\Responses\ProtectedFileResponse;

/** Централизует ошибки и запрет кеширования API с приватной ссылкой вместо Bearer-сессии. */
abstract class PrivateApiJsonController extends BaseJsonController
{
    use CreatedJsonTrait;

    protected function getExceptionResponse(): ControllerJson
    {
        return (new ApiJsonExceptionResponse(
            $this->thrownException ?? new \RuntimeException('Unknown controller exception.'),
        ))->getResponse();
    }

    public function finalizeResponse(Response|string $response): void
    {
        parent::finalizeResponse($response);
        if ($response instanceof HttpResponse) {
            $response->addHeader('Cache-Control', 'no-store');
            $response->addHeader('Referrer-Policy', 'no-referrer');
        }
    }

    final protected function preview(
        PreviewContentOutputDto $content,
    ): PreviewResponse {
        return new PreviewResponse($content);
    }

    final protected function protectedFile(ProtectedFileOutputDto $file): ProtectedFileResponse
    {
        return new ProtectedFileResponse($file);
    }
}
