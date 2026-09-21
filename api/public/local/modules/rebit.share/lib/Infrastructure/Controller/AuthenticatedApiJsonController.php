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
use Rebit\Share\Shared\Interface\ResponseDtoInterface;

/**
 * Централизует обязательную HTTP-обвязку защищённых JSON API.
 *
 * Конкретные контроллеры получают уже авторизованный actor id, единый error contract,
 * no-store и Monolog-фильтры без прямой зависимости от Bitrix и инфраструктурных сервисов.
 */
abstract class AuthenticatedApiJsonController extends BaseJsonController implements AuthenticatedControllerInterface
{
    use AuthenticatedControllerTrait;

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

    final protected function createdJson(
        array|ResponseDtoInterface $data,
        string $location,
    ): ControllerJson {
        $response = $this->json($data);
        $response->setStatus(self::HTTP_CREATED_CODE);
        $response->addHeader('Location', $location);

        return $response;
    }
}
