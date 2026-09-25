<?php

declare(strict_types=1);

namespace Morefoto\Access\Presentation\Controller;

use Morefoto\Access\Application\Staff\UseCase\ArchiveStaffUseCase;
use Morefoto\Access\Presentation\Staff\Dto\ArchiveStaffRequestDto;
use Morefoto\Access\Presentation\Staff\StaffArchiveInputMapper;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;
use Rebit\Share\Infrastructure\Controller\Responses\EmptyResponse;

/** Staff removal from the cabinet (#91). */
final class StaffArchiveController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly ArchiveStaffUseCase $archive,
        private readonly StaffArchiveInputMapper $input,
    ) {
        parent::__construct();
    }

    public function deleteAction(ArchiveStaffRequestDto $request): EmptyResponse
    {
        $this->archive->execute($this->getAuthUserId(), $this->input->bearer($request), $this->input->userId($request));

        return $this->noContent();
    }
}
