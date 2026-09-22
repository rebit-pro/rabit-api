<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media;

use Rebit\Share\Contracts\Media\Dto\GroupMaterialsOutputDto;

/** Photo readiness of groups before their gallery link is handed over. */
interface GroupMaterialsInterface
{
    /**
     * Reads without locks. The fingerprint changes with any photo, status, assignment or cover change.
     *
     * @param list<int> $groupIds native Organization group IDs
     *
     * @return array<int, GroupMaterialsOutputDto> keyed by native group ID
     */
    public function snapshots(array $groupIds): array;

    /** Inside the caller's transaction: serializes with assignment and cover commands of the shoot, then reads the group. */
    public function lock(int $shootId, int $groupId): GroupMaterialsOutputDto;
}
