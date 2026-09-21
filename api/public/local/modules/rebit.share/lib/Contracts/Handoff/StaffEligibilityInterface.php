<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Handoff;

interface StaffEligibilityInterface
{
    /** @param list<int> $childIds
     * @return array<int,bool>
     */
    public function confirmed(int $shootId, array $childIds): array;
}
