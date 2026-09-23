<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Controller;

use Morefoto\Media\Application\Photo\UseCase\ListPhotosUseCase;
use Morefoto\Media\Presentation\Photo\Dto\ListPhotosRequestDto;
use Morefoto\Media\Presentation\Photo\PhotoListInputMapper;
use Morefoto\Media\Presentation\Photo\PhotoListResultMapper;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;

final class PhotoListController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly ListPhotosUseCase $list,
        private readonly PhotoListInputMapper $inputMapper,
        private readonly PhotoListResultMapper $resultMapper,
    ) {
        parent::__construct();
    }

    public function listAction(ListPhotosRequestDto $request): ControllerJson
    {
        return $this->json($this->resultMapper->page($this->list->execute(
            $this->getAuthUserId(),
            $request->shootId,
            $this->inputMapper->list($request),
        )));
    }
}
