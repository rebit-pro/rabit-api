<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Auth;

use Rebit\Share\Shared\Exception\HttpException;

/**
 * Контракт для резолва userId по Bearer-токену.
 * Реализация в модуле rebit.auth.
 */
interface TokenResolverInterface
{
    /**
     * @throws HttpException если токен невалиден или просрочен
     */
    public function resolveUserId(string $token): int;
}
