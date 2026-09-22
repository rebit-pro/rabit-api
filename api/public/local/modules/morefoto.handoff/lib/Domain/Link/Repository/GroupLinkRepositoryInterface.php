<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Domain\Link\Repository;

use Morefoto\Handoff\Domain\Link\ValueObject\LinkHistoryEntry;
use Morefoto\Handoff\Domain\Link\ValueObject\LinkState;

interface GroupLinkRepositoryInterface
{
    /**
     * @param list<int> $groupIds native group IDs
     *
     * @return array<int, LinkState> only groups with stored state
     */
    public function states(array $groupIds): array;

    /** Locks the stored state for a command; an absent state is returned as revision 1. */
    public function lock(int $groupId): LinkState;

    /** Stores the confirmed preparation signature and returns the next revision. */
    public function prepare(int $groupId, int $expectedRevision, string $signature, int $actorId): int;

    /** Advances the revision after a recorded or corrected delivery and returns it. */
    public function advance(int $groupId, int $expectedRevision): int;

    public function appendHistory(LinkHistoryEntry $entry): void;

    /**
     * @return list<array{
     *     kind: string,
     *     actorId: int,
     *     actorName: string,
     *     at: string,
     *     sentAt: ?string,
     *     closesAt: ?string,
     *     deliveryDueAt: ?string,
     *     previousSentAt: ?string,
     *     previousClosesAt: ?string,
     *     previousDeliveryDueAt: ?string,
     *     reason: ?string,
     * }> chronological, UTC moments formatted as Y-m-d H:i:s
     */
    public function history(int $groupId): array;

    /**
     * @param list<int> $groupIds native group IDs
     *
     * @return array<int, list<array{0: string, 1: int}>> public ID and revision of unfinished staff requests per group
     */
    public function pendingStaffRequests(array $groupIds): array;

    /** @return null|array{payloadHash: string, result: string} */
    public function idempotency(int $actorId, string $resource, string $key): ?array;

    public function remember(int $actorId, string $resource, string $key, string $payloadHash, string $result): void;
}
