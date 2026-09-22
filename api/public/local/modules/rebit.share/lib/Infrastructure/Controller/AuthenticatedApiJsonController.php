<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller;

use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Response;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\Auth\AuthenticatedControllerInterface;
use Rebit\Share\Infrastructure\Controller\Auth\AuthenticatedControllerTrait;
use Rebit\Share\Infrastructure\Controller\Filters\BearerTokenFilter;
use Rebit\Share\Infrastructure\Controller\Responses\ApiJsonExceptionResponse;
use Rebit\Share\Application\Contract\File\Dto\PreviewContentOutputDto;
use Rebit\Share\Infrastructure\Controller\Responses\PreviewResponse;

/**
 * Централизует обязательную HTTP-обвязку защищённых JSON API.
 *
 * Конкретные контроллеры получают уже авторизованный actor id, единый error contract,
 * no-store и Monolog-фильтры без прямой зависимости от Bitrix и инфраструктурных сервисов.
 */
abstract class AuthenticatedApiJsonController extends BaseJsonController implements AuthenticatedControllerInterface
{
    use AuthenticatedControllerTrait;
    use CreatedJsonTrait;

    /** @return Base[] */
    protected function getDefaultPreFilters(): array
    {
        return [
            new BearerTokenFilter($this->getTokenResolver()),
            ...parent::getDefaultPreFilters(),
        ];
    }

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
        }
    }

    final protected function preview(
        PreviewContentOutputDto $content,
    ): PreviewResponse {
        return new PreviewResponse($content);
    }
}
