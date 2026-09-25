<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Controller;

use Morefoto\Media\Application\Photo\UseCase\UploadPhotoUseCase;
use Morefoto\Media\Presentation\Photo\Dto\UploadPhotoRequestDto;
use Morefoto\Media\Presentation\Photo\PhotoInputMapper;
use Morefoto\Media\Presentation\Photo\PhotoResultMapper;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;

final class PhotoUploadController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly UploadPhotoUseCase $upload,
        private readonly PhotoInputMapper $inputMapper,
        private readonly PhotoResultMapper $resultMapper,
    ) {
        parent::__construct();
    }

    public function uploadAction(UploadPhotoRequestDto $request): ControllerJson
    {
        return $this->acceptedJson($this->resultMapper->upload($this->upload->execute(
            $this->getAuthUserId(),
            $this->inputMapper->upload($request),
        )));
    }
}
