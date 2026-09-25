<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Auth;

use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Трейт реализует AuthenticatedControllerInterface.
 *
 * Подключается в контроллерах, которым нужна авторизация по Bearer-токену.
 */
trait AuthenticatedControllerTrait
{
    private ?int $authUserId = null;
    private ?TokenResolverInterface $injectedTokenResolver = null;

    final public function setTokenResolver(TokenResolverInterface $tokenResolver): void
    {
        $this->injectedTokenResolver = $tokenResolver;
    }

    final protected function getTokenResolver(): TokenResolverInterface
    {
        return $this->injectedTokenResolver
            ?? throw new \LogicException('TokenResolverInterface was not injected into authenticated controller.');
    }

    public function setAuthUserId(?int $userId): void
    {
        $this->authUserId = $userId;
    }

    /**
     * @throws HttpException
     */
    public function getAuthUserId(): int
    {
        if (null === $this->authUserId) {
            throw new HttpException('UNAUTHORIZED', 401);
        }

        return $this->authUserId;
    }

    public function getAuthUserIdOrNull(): ?int
    {
        return $this->authUserId;
    }
}
