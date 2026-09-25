<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Controller;

use Morefoto\Media\Application\Photo\UseCase\AssignPhotosUseCase;
use Morefoto\Media\Application\Photo\UseCase\SetGroupCoverUseCase;
use Morefoto\Media\Presentation\Photo\Dto\AssignPhotosRequestDto;
use Morefoto\Media\Presentation\Photo\Dto\SetGroupCoverRequestDto;
use Morefoto\Media\Presentation\Photo\PhotoInputMapper;
use Morefoto\Media\Presentation\Photo\PhotoResultMapper;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;

final class GroupMediaController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly AssignPhotosUseCase $assignments,
        private readonly SetGroupCoverUseCase $covers,
        private readonly PhotoInputMapper $inputMapper,
        private readonly PhotoResultMapper $resultMapper,
    ) {
        parent::__construct();
    }

    public function assignmentAction(AssignPhotosRequestDto $request): ControllerJson
    {
        // The body is checked before the idempotency key, as MED-05 always did.
        $input = $this->inputMapper->assignment($request);

        return $this->json($this->resultMapper->assignment($this->assignments->execute(
            $this->getAuthUserId(),
            $request->groupId,
            $this->inputMapper->key($request->idempotencyKey),
            $input,
        )));
    }

    public function coverAction(SetGroupCoverRequestDto $request): ControllerJson
    {
        $input = $this->inputMapper->cover($request);

        return $this->json($this->resultMapper->cover($this->covers->execute(
            $this->getAuthUserId(),
            $request->groupId,
            $this->inputMapper->key($request->idempotencyKey),
            $input,
        )));
    }
}
