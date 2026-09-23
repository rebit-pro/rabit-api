<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Auth;

use Rebit\Share\Application\Contract\Auth\Dto\StaffIdentityOutputDto;
use Rebit\Share\Application\Contract\Auth\Dto\StaffInvitationOutputDto;

interface StaffIdentityGatewayInterface
{
    public function findByEmailForUpdate(string $email): ?StaffIdentityOutputDto;

    public function lockById(int $userId): ?StaffIdentityOutputDto;

    public function createPending(string $email, string $name): StaffIdentityOutputDto;

    public function updateContact(int $userId, string $email, string $name): StaffIdentityOutputDto;

    public function revokeSessions(int $userId): void;

    /**
     * Issues a personal invitation link to a pending identity and queues the letter; the caller owns the transaction.
     * A repeated call within the cooldown fails with RATE_LIMITED unless `$force` is set after an address change.
     */
    public function issueInvitation(int $userId, int $issuedBy, bool $force = false): StaffInvitationOutputDto;

    /**
     * @param list<int> $userIds
     *
     * @return array<int, StaffInvitationOutputDto> keyed by user id
     */
    public function invitations(array $userIds): array;
}
