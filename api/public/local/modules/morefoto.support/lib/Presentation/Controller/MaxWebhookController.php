<?php

declare(strict_types=1);

namespace Morefoto\Support\Presentation\Controller;

use Morefoto\Support\Application\Max\UseCase\HandleMaxUpdateUseCase;
use Morefoto\Support\Infrastructure\Controller\MaxWebhookApiJsonController;
use Morefoto\Support\Presentation\Max\MaxUpdateMapper;
use Morefoto\Support\Presentation\Max\Request\Dto\MaxUpdateRequestDto;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;

final class MaxWebhookController extends MaxWebhookApiJsonController
{
    public function __construct(
        private readonly HandleMaxUpdateUseCase $handle,
        private readonly MaxUpdateMapper $mapper,
    ) {
        parent::__construct();
    }

    public function updateAction(MaxUpdateRequestDto $request): ControllerJson
    {
        $this->handle->execute($this->mapper->update($request));

        return $this->json($this->mapper->acknowledged());
    }
}
