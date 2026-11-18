<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Controller;

use Morefoto\Media\Application\Gallery\UseCase\GetShootGalleryUseCase;
use Morefoto\Media\Presentation\Gallery\Dto\ShootCoverRequestDto;
use Morefoto\Media\Presentation\Gallery\Dto\ShootGalleryRequestDto;
use Morefoto\Media\Presentation\Gallery\ShootGalleryResultMapper;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\PrivateApiJsonController;
use Rebit\Share\Infrastructure\Controller\Responses\PreviewResponse;

final class ShootGalleryController extends PrivateApiJsonController
{
    public function __construct(private readonly GetShootGalleryUseCase $gallery, private readonly ShootGalleryResultMapper $result)
    {
        parent::__construct();
    }

    public function getAction(ShootGalleryRequestDto $request): ControllerJson
    {
        return $this->json($this->result->map($this->gallery->execute($request->key), $request->key));
    }

    public function coverAction(ShootCoverRequestDto $request): PreviewResponse
    {
        return $this->preview($this->gallery->cover($request->key, $request->groupId));
    }
}
