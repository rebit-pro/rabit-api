<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Filters;

use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\HttpRequest;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Infrastructure\Controller\Auth\AuthenticatedControllerInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * PreFilter для авторизации по заголовку Authorization: Bearer <token>.
 *
 * Извлекает токен из заголовка, резолвит userId через TokenResolverInterface
 * и устанавливает его на контроллер через AuthenticatedControllerInterface.
 *
 * Режимы:
 *  - required (по умолчанию): 401, если токен отсутствует или невалиден
 *  - optional: гостевой доступ, userId = null
 */
final class BearerTokenFilter extends Base
{
    private const string HEADER_NAME = 'Authorization';
    private const string BEARER_PREFIX = 'Bearer ';

    public function __construct(
        private readonly TokenResolverInterface $tokenResolver,
        private readonly bool $required = true,
    ) {
        parent::__construct();
    }

    /**
     * @throws HttpException
     */
    public function onBeforeAction(Event $event): ?EventResult
    {
        $controller = $event->getParameter('controller');

        if (!$controller instanceof AuthenticatedControllerInterface) {
            return null;
        }

        /** @var HttpRequest $request */
        $request = $event->getParameter('controller')->getRequest();
        $token = $this->extractBearerToken($request);

        if (null === $token) {
            if ($this->required) {
                throw new HttpException('Unauthorized', 401);
            }

            return null;
        }

        $userId = $this->tokenResolver->resolveUserId($token);
        $controller->setAuthUserId($userId);

        return null;
    }

    private function extractBearerToken(HttpRequest $request): ?string
    {
        $header = $request->getHeader(self::HEADER_NAME);

        if (null === $header || '' === $header) {
            return null;
        }

        if (!str_starts_with($header, self::BEARER_PREFIX)) {
            return null;
        }

        $token = substr($header, strlen(self::BEARER_PREFIX));

        return '' !== $token ? $token : null;
    }
}
