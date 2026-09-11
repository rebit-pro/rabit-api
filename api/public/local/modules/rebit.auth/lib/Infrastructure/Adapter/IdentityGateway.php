<?php

declare(strict_types=1);

namespace Rebit\Auth\Infrastructure\Adapter;

use Rebit\Auth\Domain\User\Entity\UserRegistrationState;
use Rebit\Auth\Domain\User\Repository\UserRepository;
use Rebit\Share\Application\Contract\Auth\Dto\IdentityOutputDto;
use Rebit\Share\Application\Contract\Auth\IdentityGatewayInterface;

final readonly class IdentityGateway implements IdentityGatewayInterface
{
    public function __construct(private UserRepository $users) {}

    public function findActive(int $userId): ?IdentityOutputDto
    {
        return $this->toOutput($this->users->findById($userId));
    }

    public function lockActive(int $userId): ?IdentityOutputDto
    {
        return $this->toOutput($this->users->findByIdForUpdate($userId));
    }

    public function revokeSessions(int $userId): void
    {
        $this->users->clearToken($userId);
    }

    private function toOutput(?UserRegistrationState $identity): ?IdentityOutputDto
    {
        if (null === $identity || !$identity->isActive || $identity->isPendingRegistration) {
            return null;
        }

        return new IdentityOutputDto(id: $identity->id, name: $identity->name, email: $identity->email);
    }
}
