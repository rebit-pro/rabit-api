<?php

declare(strict_types=1);

namespace Morefoto\Access\Presentation\Staff;

use Morefoto\Access\Presentation\Staff\Dto\ArchiveStaffRequestDto;

/** Stateless mapping of the staff removal route to the staff id and the actor's session token. */
final readonly class StaffArchiveInputMapper
{
    public const string USER_ID_PATTERN = '/^[1-9]\d{0,9}$/D';

    public function userId(ArchiveStaffRequestDto $request): int
    {
        return (int)$request->userId;
    }

    /** The use case re-resolves the token under the access lock, so a session revoked while waiting is rejected. */
    public function bearer(ArchiveStaffRequestDto $request): string
    {
        return substr($request->authorization, 7);
    }
}
