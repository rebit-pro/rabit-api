<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization;

use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryPageOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryQueryInputDto;

/** Read model of groups with their calendar for staff link screens; the caller authorizes the actor scope. */
interface GroupDirectoryInterface
{
    /** Page ordered by shoot, group name and ID; state is derived from the calendar and server time. */
    public function page(GroupDirectoryQueryInputDto $query): GroupDirectoryPageOutputDto;

    /**
     * Current projection of one group or null when it does not exist. No database Result crosses this boundary.
     *
     * @phpstan-impure
     */
    public function find(string $groupId): ?GroupDirectoryItemOutputDto;
}
