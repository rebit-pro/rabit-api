<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Controller;

use Morefoto\Organization\Application\Structure\UseCase\DeleteStructureUseCase;
use Morefoto\Organization\Domain\Structure\Enum\StructureKindEnum;
use Morefoto\Organization\Presentation\Structure\Dto\DeleteGroupRequestDto;
use Morefoto\Organization\Presentation\Structure\Dto\DeleteInstitutionRequestDto;
use Morefoto\Organization\Presentation\Structure\Dto\DeleteShootRequestDto;
use Morefoto\Organization\Presentation\Structure\StructureRemovalInputMapper;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;
use Rebit\Share\Infrastructure\Controller\Responses\EmptyResponse;

/** Removal of groups, shoots and institutions from the cabinet (#92). */
final class StructureRemovalController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly DeleteStructureUseCase $delete,
        private readonly StructureRemovalInputMapper $input,
    ) {
        parent::__construct();
    }

    public function deleteGroupAction(DeleteGroupRequestDto $request): EmptyResponse
    {
        $this->delete->execute($this->getAuthUserId(), $this->input->bearer($request), StructureKindEnum::GROUP, $this->input->id($request));

        return $this->noContent();
    }

    public function deleteShootAction(DeleteShootRequestDto $request): EmptyResponse
    {
        $this->delete->execute($this->getAuthUserId(), $this->input->bearer($request), StructureKindEnum::SHOOT, $this->input->id($request));

        return $this->noContent();
    }

    public function deleteInstitutionAction(DeleteInstitutionRequestDto $request): EmptyResponse
    {
        $this->delete->execute($this->getAuthUserId(), $this->input->bearer($request), StructureKindEnum::INSTITUTION, $this->input->id($request));

        return $this->noContent();
    }
}
