<?php

declare(strict_types=1);

namespace Morefoto\Production\Presentation\Controller;

use Morefoto\Production\Application\Archive\UseCase\OpenProductionArchiveUseCase;
use Morefoto\Production\Presentation\Archive\Request\Dto\ArchiveRequestDto;
use Rebit\Share\Infrastructure\Controller\PrivateApiJsonController;
use Rebit\Share\Infrastructure\Controller\Responses\ProtectedFileResponse;

final class PublicArchiveController extends PrivateApiJsonController
{
    public function __construct(private readonly OpenProductionArchiveUseCase $open)
    {
        parent::__construct();
    }

    public function archiveAction(ArchiveRequestDto $request): ProtectedFileResponse
    {
        return $this->protectedFile($this->open->execute($request->token));
    }
}
