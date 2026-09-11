<?php

declare(strict_types=1);

namespace Rebit\Auth\Infrastructure\Adapter;

use Rebit\Auth\Domain\User\Repository\UserRepository;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Exception\RepositoryException;

/**
 * Адаптер для резолва userId по Bearer-токену.
 * Реализует межмодульный контракт из rebit.share.
 */
final readonly class TokenResolver implements TokenResolverInterface
{
    public function __construct(
        private UserRepository $repository,
    ) {}

    /**
     * @throws HttpException
     * @throws RepositoryException
     */
    public function resolveUserId(string $token): int
    {
        $userToken = $this->repository->findByToken($token);

        if (null === $userToken) {
            throw new HttpException('Unauthorized', 401);
        }

        if (null !== $userToken->expiresAt && $userToken->expiresAt->getTimestamp() < time()) {
            throw new HttpException('Token expired', 401);
        }

        return $userToken->userId;
    }
}
