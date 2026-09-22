<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Controller;

use Morefoto\Handoff\Application\Link\UseCase\CorrectGroupLinkDateUseCase;
use Morefoto\Handoff\Application\Link\UseCase\GetGroupLinkUseCase;
use Morefoto\Handoff\Application\Link\UseCase\ListGroupLinksUseCase;
use Morefoto\Handoff\Application\Link\UseCase\PrepareGroupLinkUseCase;
use Morefoto\Handoff\Application\Link\UseCase\TransmitGroupLinkUseCase;
use Morefoto\Handoff\Presentation\Link\GroupLinkInputMapper;
use Morefoto\Handoff\Presentation\Link\GroupLinkResultMapper;
use Morefoto\Handoff\Presentation\Link\Request\Dto\CorrectGroupLinkDateRequestDto;
use Morefoto\Handoff\Presentation\Link\Request\Dto\GroupLinkListRequestDto;
use Morefoto\Handoff\Presentation\Link\Request\Dto\GroupLinkRequestDto;
use Morefoto\Handoff\Presentation\Link\Request\Dto\PrepareGroupLinkRequestDto;
use Morefoto\Handoff\Presentation\Link\Request\Dto\TransmitGroupLinkRequestDto;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;

final class GroupLinkController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly ListGroupLinksUseCase $list,
        private readonly GetGroupLinkUseCase $detail,
        private readonly PrepareGroupLinkUseCase $prepare,
        private readonly TransmitGroupLinkUseCase $transmit,
        private readonly CorrectGroupLinkDateUseCase $correct,
        private readonly GroupLinkInputMapper $inputMapper,
        private readonly GroupLinkResultMapper $resultMapper,
    ) {
        parent::__construct();
    }

    public function listAction(GroupLinkListRequestDto $request): ControllerJson
    {
        $output = $this->list->execute($this->getAuthUserId(), $this->inputMapper->list($request));

        return $this->json($this->resultMapper->list($output), $this->resultMapper->meta($output));
    }

    public function detailAction(GroupLinkRequestDto $request): ControllerJson
    {
        return $this->json($this->resultMapper->detail($this->detail->execute($this->getAuthUserId(), $request->groupId)));
    }

    public function preparationAction(PrepareGroupLinkRequestDto $request): ControllerJson
    {
        return $this->json($this->resultMapper->preparation($this->prepare->execute(
            $this->getAuthUserId(),
            $request->groupId,
            $this->inputMapper->key($request),
            $this->inputMapper->preparation($request),
        )));
    }

    public function transmissionAction(TransmitGroupLinkRequestDto $request): ControllerJson
    {
        return $this->json($this->resultMapper->calendar($this->transmit->execute(
            $this->getAuthUserId(),
            $request->groupId,
            $this->inputMapper->key($request),
            $this->inputMapper->transmission($request),
        )));
    }

    public function dateCorrectionAction(CorrectGroupLinkDateRequestDto $request): ControllerJson
    {
        return $this->json($this->resultMapper->calendar($this->correct->execute(
            $this->getAuthUserId(),
            $request->groupId,
            $this->inputMapper->key($request),
            $this->inputMapper->correction($request),
        )));
    }
}
