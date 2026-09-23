<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Controller;

use Morefoto\Organization\Application\Institution\UseCase\GetInstitutionDetailUseCase;
use Morefoto\Organization\Presentation\Institution\Dto\InstitutionDetailRequestDto;
use Morefoto\Organization\Presentation\Institution\InstitutionDetailInputMapper;
use Morefoto\Organization\Presentation\Institution\Result\InstitutionDetailResultMapper;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;

final class InstitutionDetailController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly GetInstitutionDetailUseCase $detail,
        private readonly InstitutionDetailInputMapper $input,
        private readonly InstitutionDetailResultMapper $result,
    ) {
        parent::__construct();
    }

    public function detailAction(InstitutionDetailRequestDto $request): ControllerJson
    {
        return $this->json($this->result->detail($this->detail->execute(
            $this->getAuthUserId(),
            $this->input->bearer($request),
            $this->input->institutionId($request),
            $this->input->pages($request),
        )));
    }
}
