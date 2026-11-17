<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Controller;

use Morefoto\Files\Application\Institution\UseCase\DownloadInstitutionDigitalArchiveUseCase;
use Morefoto\Files\Presentation\Institution\Request\Dto\InstitutionDigitalArchiveTicketRequestDto;
use Rebit\Share\Infrastructure\Controller\PrivateApiJsonController;
use Rebit\Share\Infrastructure\Controller\Responses\ProtectedFileResponse;

final class InstitutionDigitalArchiveContentController extends PrivateApiJsonController
{
    public function __construct(private readonly DownloadInstitutionDigitalArchiveUseCase $download)
    {
        parent::__construct();
    }

    public function contentAction(InstitutionDigitalArchiveTicketRequestDto $request): ProtectedFileResponse
    {
        return $this->protectedFile($this->download->open($request->ticket));
    }
}
