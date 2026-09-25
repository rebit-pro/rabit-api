<?php

declare(strict_types=1);

namespace Morefoto\Access\Presentation\Staff;

use Morefoto\Access\Presentation\Staff\Dto\ArchiveStaffRequestDto;

/** Stateless mapping of the staff removal route to the staff id. */
final readonly class StaffArchiveInputMapper
{
    public const string USER_ID_PATTERN = '/^[1-9]\d{0,9}$/D';

    public function userId(ArchiveStaffRequestDto $request): int
    {
        return (int)$request->userId;
    }
}
