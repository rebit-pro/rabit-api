<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Controller;

use Morefoto\Media\Application\Photo\UseCase\GetPhotoUseCase;
use Morefoto\Media\Presentation\Photo\Dto\PhotoRequestDto;
use Morefoto\Media\Presentation\Photo\PhotoResultMapper;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;

final class PhotoDetailController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly GetPhotoUseCase $detail,
        private readonly PhotoResultMapper $resultMapper,
    ) {
        parent::__construct();
    }

    public function detailAction(PhotoRequestDto $request): ControllerJson
    {
        return $this->json($this->resultMapper->photo($this->detail->execute($this->getAuthUserId(), $request->photoId)));
    }
}
