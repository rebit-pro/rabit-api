<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Controller;

use Morefoto\Handoff\Application\Request\UseCase\ClarifyStaffRequestUseCase;
use Morefoto\Handoff\Application\Request\UseCase\GetStaffRequestUseCase;
use Morefoto\Handoff\Application\Request\UseCase\ListStaffRequestsUseCase;
use Morefoto\Handoff\Application\Request\UseCase\SaveStaffRequestUseCase;
use Morefoto\Handoff\Presentation\Request\Dto\ClarifyStaffRequestRequestDto;
use Morefoto\Handoff\Presentation\Request\Dto\CreateStaffRequestRequestDto;
use Morefoto\Handoff\Presentation\Request\Dto\StaffRequestDetailRequestDto;
use Morefoto\Handoff\Presentation\Request\Dto\StaffRequestListRequestDto;
use Morefoto\Handoff\Presentation\Request\Dto\UpdateStaffRequestRequestDto;
use Morefoto\Handoff\Presentation\Request\StaffRequestInputMapper;
use Morefoto\Handoff\Presentation\Result\StaffRequestResultMapper;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;

final class StaffRequestController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly ListStaffRequestsUseCase $list,
        private readonly GetStaffRequestUseCase $detail,
        private readonly SaveStaffRequestUseCase $save,
        private readonly ClarifyStaffRequestUseCase $clarify,
        private readonly StaffRequestInputMapper $inputMapper,
        private readonly StaffRequestResultMapper $resultMapper,
    ) {
        parent::__construct();
    }

    public function listAction(StaffRequestListRequestDto $request): ControllerJson
    {
        $output = $this->list->execute($this->getAuthUserId(), $this->inputMapper->list($request));

        return $this->json($this->resultMapper->list($output), $this->resultMapper->meta($output));
    }

    public function detailAction(StaffRequestDetailRequestDto $request): ControllerJson
    {
        return $this->json($this->resultMapper->detail($this->detail->execute($this->getAuthUserId(), $request->requestId)));
    }

    public function createAction(CreateStaffRequestRequestDto $request): ControllerJson
    {
        $output = $this->save->execute(
            $this->getAuthUserId(),
            null,
            $this->inputMapper->key($request),
            $this->inputMapper->create($request),
        );

        return $this->createdJson($this->resultMapper->mutation($output), $this->resultMapper->location($output));
    }

    public function updateAction(UpdateStaffRequestRequestDto $request): ControllerJson
    {
        return $this->json($this->resultMapper->mutation($this->save->execute(
            $this->getAuthUserId(),
            $request->requestId,
            $this->inputMapper->key($request),
            $this->inputMapper->update($request),
        )));
    }

    public function clarificationAction(ClarifyStaffRequestRequestDto $request): ControllerJson
    {
        return $this->json($this->resultMapper->mutation($this->clarify->execute(
            $this->getAuthUserId(),
            $request->requestId,
            $this->inputMapper->key($request),
            $this->inputMapper->clarify($request),
        )));
    }
}
