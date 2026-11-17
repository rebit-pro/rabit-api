<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Controller;

use Morefoto\Files\Application\Institution\UseCase\CreateInstitutionDigitalArchiveUseCase;
use Morefoto\Files\Application\Institution\UseCase\DownloadInstitutionDigitalArchiveUseCase;
use Morefoto\Files\Application\Institution\UseCase\ExportInstitutionDigitalArchiveUseCase;
use Morefoto\Files\Application\Institution\UseCase\GetInstitutionDigitalArchiveUseCase;
use Morefoto\Files\Application\Institution\UseCase\ListInstitutionDigitalArchivesUseCase;
use Morefoto\Files\Presentation\Institution\DigitalArchiveResultMapper;
use Morefoto\Files\Presentation\Institution\Request\Dto\CreateInstitutionDigitalArchiveRequestDto;
use Morefoto\Files\Presentation\Institution\Request\Dto\InstitutionDigitalArchiveRequestDto;
use Morefoto\Files\Presentation\Institution\Request\Dto\InstitutionDigitalArchivesRequestDto;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;
use Rebit\Share\Infrastructure\Controller\Responses\TextFileResponse;

final class InstitutionDigitalArchiveController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly ListInstitutionDigitalArchivesUseCase $listing,
        private readonly CreateInstitutionDigitalArchiveUseCase $create,
        private readonly GetInstitutionDigitalArchiveUseCase $get,
        private readonly DownloadInstitutionDigitalArchiveUseCase $download,
        private readonly ExportInstitutionDigitalArchiveUseCase $export,
        private readonly DigitalArchiveResultMapper $result,
    ) {
        parent::__construct();
    }

    public function listAction(InstitutionDigitalArchivesRequestDto $request): ControllerJson
    {
        return $this->json($this->result->listing($this->listing->execute($this->getAuthUserId(), $request->institutionId)));
    }

    public function createAction(CreateInstitutionDigitalArchiveRequestDto $request): ControllerJson
    {
        return $this->json($this->result->archive($this->create->execute($this->getAuthUserId(), $request->institutionId, $request->idempotencyKey)))->setStatus(self::HTTP_ACCEPTED_CODE);
    }

    public function archiveAction(InstitutionDigitalArchiveRequestDto $request): ControllerJson
    {
        return $this->json($this->result->archive($this->get->execute($this->getAuthUserId(), $request->archiveId)));
    }

    public function downloadAction(InstitutionDigitalArchiveRequestDto $request): ControllerJson
    {
        return $this->json($this->result->link($this->download->link($this->getAuthUserId(), $request->archiveId)));
    }

    public function manifestAction(InstitutionDigitalArchiveRequestDto $request): TextFileResponse
    {
        return $this->textFile($this->export->execute($this->getAuthUserId(), $request->archiveId));
    }
}
