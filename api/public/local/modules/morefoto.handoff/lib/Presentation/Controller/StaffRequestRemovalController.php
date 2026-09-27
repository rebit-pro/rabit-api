<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Controller;

use Morefoto\Handoff\Application\Request\UseCase\DeleteStaffRequestUseCase;
use Morefoto\Handoff\Presentation\Request\Dto\StaffRequestDetailRequestDto;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;
use Rebit\Share\Infrastructure\Controller\Responses\EmptyResponse;

/** Removal of a staff list that is not transferred yet (#92 DEC-01). */
final class StaffRequestRemovalController extends AuthenticatedApiJsonController
{
    public function __construct(private readonly DeleteStaffRequestUseCase $delete)
    {
        parent::__construct();
    }

    public function deleteAction(StaffRequestDetailRequestDto $request): EmptyResponse
    {
        $this->delete->execute($this->getAuthUserId(), $request->requestId);

        return $this->noContent();
    }
}
