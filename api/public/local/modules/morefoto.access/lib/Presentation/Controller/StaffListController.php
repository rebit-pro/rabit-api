<?php

declare(strict_types=1);

namespace Morefoto\Access\Presentation\Controller;

use Morefoto\Access\Application\Staff\UseCase\StaffDirectoryUseCase;
use Morefoto\Access\Presentation\Staff\Dto\StaffListRequestDto;
use Morefoto\Access\Presentation\Staff\Result\StaffListResultMapper;
use Morefoto\Access\Presentation\Staff\StaffListInputMapper;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;

final class StaffListController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly StaffDirectoryUseCase $directory,
        private readonly StaffListInputMapper $input,
        private readonly StaffListResultMapper $result,
    ) {
        parent::__construct();
    }

    public function listAction(StaffListRequestDto $request): ControllerJson
    {
        $output = $this->directory->list($this->getAuthUserId(), $this->input->list($request));

        return $this->json($this->result->list($output), $this->result->meta($output));
    }
}
