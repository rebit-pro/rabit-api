<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Commerce;

use Rebit\Share\Contracts\Commerce\Dto\SalesReadinessOutputDto;

/** Effective sales conditions of groups for link preparation; the fingerprint changes with any price or rule change. */
interface GroupSalesReadinessInterface
{
    /**
     * Reads with share locks: inside the caller's transaction they hold until commit and block concurrent condition changes.
     *
     * @param list<int> $groupIds native Organization group IDs
     *
     * @return array<int, SalesReadinessOutputDto> keyed by native group ID
     */
    public function readiness(array $groupIds): array;
}
