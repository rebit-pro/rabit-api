<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Access\Contract;

use Rebit\Auth\Domain\Access\Entity\AccessLink;
use Rebit\Auth\Domain\Access\Enum\AccessLinkPurposeEnum;

/** Storage of personal access links; callers own the transaction for the `ForUpdate` reads and writes. */
interface AccessLinkRepositoryInterface
{
    public function findByTokenHash(string $tokenHash): ?AccessLink;

    public function findByTokenHashForUpdate(string $tokenHash): ?AccessLink;

    public function findForUserForUpdate(int $userId, AccessLinkPurposeEnum $purpose): ?AccessLink;

    /** Stores the link of this user and purpose; the previous token of the same purpose stops working. */
    public function replace(
        int $userId,
        AccessLinkPurposeEnum $purpose,
        string $tokenHash,
        int $issuedAt,
        int $expiresAt,
        int $resendAvailableAt,
        ?int $issuedBy,
    ): void;

    public function markUsed(int $id, int $usedAt): void;

    /**
     * @param list<int> $userIds
     *
     * @return array<int, AccessLink> invitation links keyed by user id
     */
    public function invitationsFor(array $userIds): array;
}
