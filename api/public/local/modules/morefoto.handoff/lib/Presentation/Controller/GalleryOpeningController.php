<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Controller;

use Morefoto\Handoff\Application\Link\UseCase\ManageGalleryOpeningUseCase;
use Morefoto\Handoff\Application\Link\UseCase\RecordManualDeliveryUseCase;
use Morefoto\Handoff\Presentation\Link\GroupLinkInputMapper;
use Morefoto\Handoff\Presentation\Link\Request\Dto\RecordManualDeliveryRequestDto;
use Morefoto\Handoff\Presentation\Link\OpeningInputMapper;
use Morefoto\Handoff\Presentation\Link\OpeningResultMapper;
use Morefoto\Handoff\Presentation\Link\Request\Dto\CancelGalleryOpeningRequestDto;
use Morefoto\Handoff\Presentation\Link\Request\Dto\ScheduleGalleryOpeningRequestDto;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;

final class GalleryOpeningController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly ManageGalleryOpeningUseCase $manage,
        private readonly OpeningInputMapper $input,
        private readonly OpeningResultMapper $result,
        private readonly RecordManualDeliveryUseCase $manual,
        private readonly GroupLinkInputMapper $linkInput,
    ) {
        parent::__construct();
    }

    public function scheduleAction(ScheduleGalleryOpeningRequestDto $request): ControllerJson
    {
        return $this->json($this->result->map($this->manage->schedule(
            $this->getAuthUserId(),
            $request->groupId,
            $this->input->key($request),
            $this->input->schedule($request),
        )));
    }

    public function cancelAction(CancelGalleryOpeningRequestDto $request): ControllerJson
    {
        return $this->json($this->result->map($this->manage->cancel(
            $this->getAuthUserId(),
            $request->groupId,
            $this->input->key($request),
            $this->input->cancel($request),
        )));
    }

    public function manualDeliveryAction(RecordManualDeliveryRequestDto $request): ControllerJson
    {
        return $this->json($this->result->manual($this->manual->execute(
            $this->getAuthUserId(),
            $request->groupId,
            $this->linkInput->key($request),
            $this->linkInput->transmission($request),
        )));
    }
}
