<?php

declare(strict_types=1);

namespace Morefoto\Access\Presentation\Controller;

use Morefoto\Access\Application\Staff\UseCase\ResendStaffInvitationUseCase;
use Morefoto\Access\Presentation\Staff\Dto\ResendStaffInvitationRequestDto;
use Morefoto\Access\Presentation\Staff\StaffInvitationInputMapper;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;

/** Repeated staff invitation (ACC-11). */
final class StaffInvitationController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly ResendStaffInvitationUseCase $resend,
        private readonly StaffInvitationInputMapper $input,
    ) {
        parent::__construct();
    }

    public function resendAction(ResendStaffInvitationRequestDto $request): ControllerJson
    {
        return $this->json($this->resend->execute($this->getAuthUserId(), $this->input->userId($request)));
    }
}
