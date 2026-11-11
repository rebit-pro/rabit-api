<?php

declare(strict_types=1);

namespace Morefoto\Production\Presentation\Controller;

use Morefoto\Production\Application\Institution\UseCase\DownloadInstitutionArchiveUseCase;
use Morefoto\Production\Presentation\Institution\Request\Dto\InstitutionArchiveTicketRequestDto;
use Rebit\Share\Infrastructure\Controller\PrivateApiJsonController;
use Rebit\Share\Infrastructure\Controller\Responses\ProtectedFileResponse;

final class InstitutionArchiveContentController extends PrivateApiJsonController
{
    public function __construct(private readonly DownloadInstitutionArchiveUseCase $download)
    {
        parent::__construct();
    }

    public function contentAction(InstitutionArchiveTicketRequestDto $request): ProtectedFileResponse
    {
        return $this->protectedFile($this->download->open($request->ticket));
    }
}
