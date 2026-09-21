<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Controller;

use Morefoto\Media\Application\Gallery\UseCase\GetManagedPreviewUseCase;
use Morefoto\Media\Presentation\Gallery\Dto\ManagedPreviewRequestDto;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;
use Rebit\Share\Infrastructure\Controller\Responses\PreviewResponse;

final class ManagedPreviewController extends AuthenticatedApiJsonController
{
    public function __construct(private readonly GetManagedPreviewUseCase $get)
    {
        parent::__construct();
    }

    public function getAction(ManagedPreviewRequestDto $request): PreviewResponse
    {
        return $this->preview($this->get->execute($this->getAuthUserId(), $request->photoId, $request->variant));
    }
}
