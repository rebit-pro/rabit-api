<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Controller;

use Morefoto\Handoff\Application\Link\UseCase\ShootGalleryLinkUseCase;
use Morefoto\Handoff\Presentation\Link\Request\Dto\ShootGalleryLinkRequestDto;
use Morefoto\Handoff\Presentation\Link\Result\Dto\ShootGalleryLinkResultDto;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;

final class ShootGalleryLinkController extends AuthenticatedApiJsonController
{
    public function __construct(private readonly ShootGalleryLinkUseCase $link)
    {
        parent::__construct();
    }

    public function getAction(ShootGalleryLinkRequestDto $request): ControllerJson
    {
        return $this->json(new ShootGalleryLinkResultDto('/g/s/' . $this->link->execute($this->getAuthUserId(), $request->shootId, false)));
    }

    public function issueAction(ShootGalleryLinkRequestDto $request): ControllerJson
    {
        return $this->json(new ShootGalleryLinkResultDto('/g/s/' . $this->link->execute($this->getAuthUserId(), $request->shootId, true)));
    }
}
