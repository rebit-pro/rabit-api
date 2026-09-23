<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Controller;

use Morefoto\Media\Application\Transfer\UseCase\TransferChildUseCase;
use Morefoto\Media\Presentation\Transfer\ChildTransferInputMapper;
use Morefoto\Media\Presentation\Transfer\ChildTransferResultMapper;
use Morefoto\Media\Presentation\Transfer\Dto\TransferChildRequestDto;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;

final class ChildTransferController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly TransferChildUseCase $transfer,
        private readonly ChildTransferInputMapper $inputMapper,
        private readonly ChildTransferResultMapper $resultMapper,
    ) {
        parent::__construct();
    }

    public function transferAction(TransferChildRequestDto $request): ControllerJson
    {
        return $this->json($this->resultMapper->transfer($this->transfer->execute(
            $this->getAuthUserId(),
            $this->inputMapper->key($request),
            $this->inputMapper->transfer($request),
        )));
    }
}
