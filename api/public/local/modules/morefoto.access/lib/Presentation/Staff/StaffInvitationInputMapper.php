<?php

declare(strict_types=1);

namespace Morefoto\Access\Presentation\Staff;

use Morefoto\Access\Presentation\Staff\Dto\ResendStaffInvitationRequestDto;

/** Stateless mapping of the invitation resend route to the staff id. */
final readonly class StaffInvitationInputMapper
{
    public const string USER_ID_PATTERN = '/^[1-9]\d{0,9}$/D';

    public function userId(ResendStaffInvitationRequestDto $request): int
    {
        return (int)$request->userId;
    }
}
