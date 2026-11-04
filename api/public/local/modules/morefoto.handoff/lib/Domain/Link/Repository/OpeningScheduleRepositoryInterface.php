<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Domain\Link\Repository;

use Morefoto\Handoff\Domain\Link\ValueObject\OpeningSchedule;

interface OpeningScheduleRepositoryInterface
{
    /** @param list<int> $groupIds
     * @return array<int, OpeningSchedule>
     */
    public function forGroups(array $groupIds): array;

    public function find(int $groupId, bool $forUpdate = false): ?OpeningSchedule;

    public function findByPublicId(string $groupId): ?OpeningSchedule;

    /** @return list<string> public group IDs, oldest opening first */
    public function due(\DateTimeImmutable $now, int $limit): array;

    public function schedule(int $groupId, \DateTimeImmutable $openAt, int $actorId): void;

    public function cancel(int $groupId): void;

    public function block(int $groupId): void;

    public function opened(int $groupId, \DateTimeImmutable $now): void;

    public function recordManual(int $groupId, \DateTimeImmutable $sentAt, int $actorId): void;
}
