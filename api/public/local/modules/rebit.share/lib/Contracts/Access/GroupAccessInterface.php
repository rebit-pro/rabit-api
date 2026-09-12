<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Access;

use Rebit\Share\Contracts\Access\Dto\GroupAssignmentOutputDto;

interface GroupAccessInterface
{
    /** Caller owns one local transaction; participants never commit it. */
    public function lockState(): string;

    public function signature(): string;

    /** @param list<int> $groupIds
     * @return array<int, GroupAssignmentOutputDto>
     */
    public function assignments(array $groupIds): array;

    /**
     * After AccessState and Organization parent locks, lock profiles then Auth identities by user ID.
     * Revalidate the actor's Bearer and organizer role, including before an idempotency replay.
     *
     * @param list<int> $userIds
     */
    public function lockParticipants(int $actorUserId, string $bearer, array $userIds): void;

    /**
     * Must run after lockState, Institution/Shoot/Group locks and lockParticipants for old/new teacher.
     * An occupied replacement or removal requires confirmation and a nonblank reason.
     * Organization verifies the group ancestry; Access owns assignments, their history and revocation.
     */
    public function replace(int $groupId, GroupAssignmentOutputDto $desired, ?string $expectedSignature, bool $replaceOccupied, int $actorUserId, string $operationId, ?string $reason): string;
}
