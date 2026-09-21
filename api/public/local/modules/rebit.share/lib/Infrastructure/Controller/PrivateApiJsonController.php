<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller;

use Bitrix\Main\HttpResponse;
use Bitrix\Main\Response;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\Responses\ApiJsonExceptionResponse;
use Rebit\Share\Application\Contract\File\Dto\PreviewContentOutputDto;
use Rebit\Share\Infrastructure\Controller\Responses\PreviewResponse;

/** Централизует ошибки и запрет кеширования API с приватной ссылкой вместо Bearer-сессии. */
abstract class PrivateApiJsonController extends BaseJsonController
{
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
}
