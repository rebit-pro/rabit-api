<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Controller;

use Morefoto\Organization\Application\Structure\UseCase\ListAllShootsUseCase;
use Morefoto\Organization\Presentation\Shoot\Dto\ShootListRequestDto;
use Morefoto\Organization\Presentation\Shoot\Result\ShootListResultMapper;
use Morefoto\Organization\Presentation\Shoot\ShootListInputMapper;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;

/** Cabinet-wide list of shoots for the «Съёмки» section (UI-DEC-03). */
final class ShootListController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly ListAllShootsUseCase $list,
        private readonly ShootListInputMapper $input,
        private readonly ShootListResultMapper $result,
    ) {
        parent::__construct();
    }

    public function listAction(ShootListRequestDto $request): ControllerJson
    {
        $output = $this->list->execute($this->getAuthUserId(), $this->input->bearer($request), $this->input->list($request));

        return $this->json($this->result->list($output), $this->result->meta($output));
    }
}
