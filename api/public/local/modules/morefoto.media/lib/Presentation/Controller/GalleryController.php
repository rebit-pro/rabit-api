<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Controller;

use Morefoto\Media\Application\Gallery\UseCase\GetGalleryUseCase;
use Morefoto\Media\Presentation\Gallery\Dto\GalleryRequestDto;
use Morefoto\Media\Presentation\Gallery\GalleryResultMapper;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\PrivateApiJsonController;
use Morefoto\Media\Application\Gallery\UseCase\GetGalleryPreviewUseCase;
use Morefoto\Media\Presentation\Gallery\Dto\GalleryPreviewRequestDto;
use Rebit\Share\Infrastructure\Controller\Responses\PreviewResponse;

final class GalleryController extends PrivateApiJsonController
{
    public function __construct(private readonly GetGalleryUseCase $get, private readonly GalleryResultMapper $mapper, private readonly GetGalleryPreviewUseCase $getPreview)
    {
        parent::__construct();
    }

    public function getAction(GalleryRequestDto $request): ControllerJson
    {
        return $this->json($this->mapper->map($this->get->execute($request->token), $request->token));
    }

    public function previewAction(GalleryPreviewRequestDto $request): PreviewResponse
    {
        return $this->preview($this->getPreview->execute($request->token, $request->assignmentId, $request->variant));
    }
}
