<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Controller;

use Morefoto\Organization\Application\Calendar\UseCase\ChangeGroupCalendarUseCase;
use Morefoto\Organization\Application\Calendar\UseCase\GetGroupCalendarUseCase;
use Morefoto\Organization\Presentation\Calendar\CalendarInputMapper;
use Morefoto\Organization\Presentation\Calendar\CalendarResultMapper;
use Morefoto\Organization\Presentation\Calendar\Request\Dto\ExtendGroupCalendarRequestDto;
use Morefoto\Organization\Presentation\Calendar\Request\Dto\GetGroupCalendarRequestDto;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;

final class GroupCalendarController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly GetGroupCalendarUseCase $get,
        private readonly ChangeGroupCalendarUseCase $change,
        private readonly CalendarInputMapper $input,
        private readonly CalendarResultMapper $result,
    ) {
        parent::__construct();
    }

    public function getAction(GetGroupCalendarRequestDto $request): ControllerJson
    {
        return $this->json($this->result->details($this->get->execute($this->getAuthUserId(), $request->groupId, $this->input->bearer($request))));
    }

    public function extendAction(ExtendGroupCalendarRequestDto $request): ControllerJson
    {
        return $this->json($this->result->mutation($this->change->extendDays(
            $this->input->command($request, $this->getAuthUserId()),
            $request->days,
            $this->input->bearer($request),
        )));
    }
}
