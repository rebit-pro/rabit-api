<?php

declare(strict_types=1);

namespace Rebit\Auth\Infrastructure\Adapter;

use Rebit\Auth\Application\Auth\Contract\ClockInterface;
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
        private ClockInterface $clock,
    ) {}

    /**
     * @throws HttpException
     * @throws RepositoryException
     */
    public function resolveUserId(string $token): int
    {
        if ('' === $token) {
            throw new HttpException('UNAUTHORIZED', 401);
        }

        $userToken = $this->repository->findByToken($token);

        // A token that no longer matches was replaced by a newer sign-in or revoked by the organizer.
        if (null === $userToken) {
            throw new HttpException('SESSION_REVOKED', 401);
        }

        if (null === $userToken->expiresAt || $userToken->expiresAt->getTimestamp() <= $this->clock->now()) {
            throw new HttpException('TOKEN_EXPIRED', 401);
        }

        return $userToken->userId;
    }
}
