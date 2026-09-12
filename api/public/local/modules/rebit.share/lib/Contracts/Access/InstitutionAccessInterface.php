<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Access;

use Rebit\Share\Contracts\Access\Dto\InstitutionAssignmentOutputDto;
use Rebit\Share\Contracts\Access\Dto\InstitutionScopeOutputDto;

interface InstitutionAccessInterface
{
    /** Caller owns one local transaction; participants never commit it. */
    public function lockState(): string;

    public function signature(): string;

    public function scope(int $userId): InstitutionScopeOutputDto;

    /** @param list<int> $institutionIds
     * @return array<int, InstitutionAssignmentOutputDto>
     */
    public function assignments(array $institutionIds): array;

    /** Lock all profiles then Auth identities in ascending user ID order; recheck actor's Bearer. @param list<int> $userIds */
    public function lockParticipants(int $actorUserId, string $bearer, array $userIds): void;

    /** Must run after lockState, parent lock and lockParticipants. */
    public function replace(int $institutionId, InstitutionAssignmentOutputDto $desired, ?string $expectedSignature, bool $replaceOccupied, int $actorUserId, string $operationId): string;
}
