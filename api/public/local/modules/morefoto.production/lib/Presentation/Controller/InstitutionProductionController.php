<?php

declare(strict_types=1);

namespace Morefoto\Production\Presentation\Controller;

use Morefoto\Production\Application\Institution\UseCase\CreateInstitutionSupplementUseCase;
use Morefoto\Production\Application\Institution\UseCase\DownloadInstitutionArchiveUseCase;
use Morefoto\Production\Application\Institution\UseCase\ExportInstitutionArchiveUseCase;
use Morefoto\Production\Application\Institution\UseCase\GetInstitutionProductionUseCase;
use Morefoto\Production\Application\Institution\UseCase\RenewInstitutionArchiveUseCase;
use Morefoto\Production\Presentation\Institution\InstitutionResultMapper;
use Morefoto\Production\Presentation\Institution\Request\Dto\InstitutionArchiveRequestDto;
use Morefoto\Production\Presentation\Institution\Request\Dto\InstitutionArchiveRenewalRequestDto;
use Morefoto\Production\Presentation\Institution\Request\Dto\InstitutionProductionRequestDto;
use Morefoto\Production\Presentation\Institution\Request\Dto\InstitutionSupplementRequestDto;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;
use Rebit\Share\Infrastructure\Controller\Responses\TextFileResponse;

final class InstitutionProductionController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly GetInstitutionProductionUseCase $get,
        private readonly CreateInstitutionSupplementUseCase $supplement,
        private readonly RenewInstitutionArchiveUseCase $renew,
        private readonly DownloadInstitutionArchiveUseCase $download,
        private readonly ExportInstitutionArchiveUseCase $export,
        private readonly InstitutionResultMapper $result,
    ) {
        parent::__construct();
    }

    public function institutionAction(InstitutionProductionRequestDto $request): ControllerJson
    {
        return $this->json($this->result->production($this->get->execute($this->getAuthUserId(), $request->institutionId)));
    }

    public function supplementAction(InstitutionSupplementRequestDto $request): ControllerJson
    {
        return $this->json($this->result->version($this->supplement->execute($this->getAuthUserId(), $request->institutionId, $request->idempotencyKey)));
    }

    public function renewalAction(InstitutionArchiveRenewalRequestDto $request): ControllerJson
    {
        return $this->json($this->result->version($this->renew->execute($this->getAuthUserId(), $request->archiveId, $request->idempotencyKey)));
    }

    public function downloadAction(InstitutionArchiveRequestDto $request): ControllerJson
    {
        return $this->json($this->result->download($this->download->link($this->getAuthUserId(), $request->archiveId)));
    }

    public function exportAction(InstitutionArchiveRequestDto $request): TextFileResponse
    {
        return $this->textFile($this->export->execute($this->getAuthUserId(), $request->archiveId));
    }
}
