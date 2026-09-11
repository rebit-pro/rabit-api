<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Auth;

use Rebit\Share\Shared\Exception\HttpException;

/**
 * Трейт реализует AuthenticatedControllerInterface.
 *
 * Подключается в контроллерах, которым нужна авторизация по Bearer-токену.
 */
trait AuthenticatedControllerTrait
{
    private ?int $authUserId = null;

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
            throw new HttpException('Unauthorized', 401);
        }

        return $this->authUserId;
    }

    public function getAuthUserIdOrNull(): ?int
    {
        return $this->authUserId;
    }
}
