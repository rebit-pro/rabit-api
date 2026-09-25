<?php

declare(strict_types=1);

namespace Rebit\Auth\Infrastructure\Adapter;

use Rebit\Auth\Application\Access\Contract\AccessLinkRepositoryInterface;
use Rebit\Auth\Application\Access\UseCase\IssueAccessInvitationUseCase;
use Rebit\Auth\Application\Auth\Contract\ClockInterface;
use Rebit\Auth\Domain\User\Entity\UserRegistrationState;
use Rebit\Auth\Domain\User\Repository\UserRepository;
use Rebit\Share\Application\Contract\Auth\Dto\StaffIdentityOutputDto;
use Rebit\Share\Application\Contract\Auth\Dto\StaffInvitationOutputDto;
use Rebit\Share\Application\Contract\Auth\StaffIdentityGatewayInterface;

final readonly class StaffIdentityGateway implements StaffIdentityGatewayInterface
{
    public function __construct(
        private UserRepository $users,
        private IssueAccessInvitationUseCase $invitation,
        private AccessLinkRepositoryInterface $links,
        private ClockInterface $clock,
    ) {}

    public function findByEmailForUpdate(string $email): ?StaffIdentityOutputDto
    {
        return $this->output($this->users->findByEmailForUpdate($email));
    }

    public function lockById(int $userId): ?StaffIdentityOutputDto
    {
        return $this->output($this->users->findByIdForUpdate($userId));
    }

    public function createPending(string $email, string $name): StaffIdentityOutputDto
    {
        $userId = $this->users->createInactiveUser($email, bin2hex(random_bytes(32)), $name);

        return $this->lockById($userId)
            ?? throw new \RuntimeException('Created Auth identity is unavailable.');
    }

    public function updateContact(int $userId, string $email, string $name): StaffIdentityOutputDto
    {
        $this->users->updateStaffContact($userId, $email, $name);

        return $this->lockById($userId)
            ?? throw new \RuntimeException('Updated Auth identity is unavailable.');
    }

    public function revokeSessions(int $userId): void
    {
        $this->users->clearToken($userId);
    }

    public function issueInvitation(int $userId, int $issuedBy, bool $force = false): StaffInvitationOutputDto
    {
        $state = $this->invitation->execute($userId, $issuedBy, $force);

        return new StaffInvitationOutputDto($state->userId, $state->sentAt, $state->expiresAt, $state->state);
    }

    public function invitations(array $userIds): array
    {
        $now = $this->clock->now();
        $output = [];
        foreach ($this->links->invitationsFor($userIds) as $userId => $link) {
            $output[$userId] = new StaffInvitationOutputDto(
                userId: $userId,
                sentAt: (new \DateTimeImmutable('@' . $link->issuedAt))->format(DATE_ATOM),
                expiresAt: (new \DateTimeImmutable('@' . $link->expiresAt))->format(DATE_ATOM),
                state: $link->isUsed() ? 'accepted' : ($link->isExpired($now) ? 'expired' : 'sent'),
            );
        }

        return $output;
    }

    private function output(?UserRegistrationState $identity): ?StaffIdentityOutputDto
    {
        return null === $identity ? null : new StaffIdentityOutputDto(
            id: $identity->id,
            name: $identity->name,
            email: $identity->email,
            active: $identity->isActive,
            pending: $identity->isPendingRegistration,
        );
    }
}
