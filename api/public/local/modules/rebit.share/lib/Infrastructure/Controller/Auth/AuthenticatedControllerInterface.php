<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Auth;

use Rebit\Share\Shared\Exception\HttpException;

/**
 * Интерфейс для контроллеров, поддерживающих авторизацию по Bearer-токену.
 *
 * Используется совместно с BearerTokenFilter.
 */
interface AuthenticatedControllerInterface
{
    public function setAuthUserId(?int $userId): void;

    /**
     * Возвращает userId авторизованного пользователя.
     *
     * @throws HttpException если пользователь не авторизован
     */
    public function getAuthUserId(): int;

    /**
     * Возвращает userId или null для гостевого доступа.
     */
    public function getAuthUserIdOrNull(): ?int;
}
