<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Staff\Contract;

use Morefoto\Access\Application\Staff\Dto\AssignmentDirectoryOutputDto;

interface AssignmentDirectoryInterface
{
    /** Organization rows are locked only inside an Access-owned mutation transaction. */
    public function snapshot(bool $lock = false): AssignmentDirectoryOutputDto;
}
