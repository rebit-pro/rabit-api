<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Controller;

use Morefoto\Organization\Application\Structure\UseCase\ImportGroupsUseCase;
use Morefoto\Organization\Presentation\Structure\Dto\ImportGroupsRequestDto;
use Morefoto\Organization\Presentation\Structure\Dto\ImportGroupsResultDto;
use Morefoto\Organization\Presentation\Structure\ImportGroupsInputMapper;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;

final class ImportGroupsController extends AuthenticatedApiJsonController
{
    public function __construct(private readonly ImportGroupsUseCase $import, private readonly ImportGroupsInputMapper $input)
    {
        parent::__construct();
    }

    public function importAction(ImportGroupsRequestDto $request): ControllerJson
    {
        return $this->json(new ImportGroupsResultDto($this->import->execute($this->getAuthUserId(), $this->input->map($request))));
    }
}
