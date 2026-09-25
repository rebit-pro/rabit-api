<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Controller;

use Morefoto\Commerce\Application\Conditions\UseCase\ManageConditionsUseCase;
use Morefoto\Commerce\Presentation\Conditions\ConditionsInputMapper;
use Morefoto\Commerce\Presentation\Conditions\ConditionsResultMapper;
use Morefoto\Commerce\Presentation\Conditions\Dto\GlobalConditionsRequestDto;
use Morefoto\Commerce\Presentation\Conditions\Dto\GroupConditionsRequestDto;
use Morefoto\Commerce\Presentation\Conditions\Dto\SaveGlobalConditionsRequestDto;
use Morefoto\Commerce\Presentation\Conditions\Dto\SaveGroupConditionsRequestDto;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;

final class ConditionsController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly ManageConditionsUseCase $conditions,
        private readonly ConditionsInputMapper $input,
        private readonly ConditionsResultMapper $result,
    ) {
        parent::__construct();
    }

    public function globalAction(GlobalConditionsRequestDto $request): ControllerJson
    {
        $output = $this->conditions->getGlobal($this->getAuthUserId(), $this->input->token($request->authorization));

        return $this->json($this->result->global($output));
    }

    public function saveGlobalAction(SaveGlobalConditionsRequestDto $request): ControllerJson
    {
        $output = $this->conditions->saveGlobal(
            $this->getAuthUserId(),
            $this->input->token($request->authorization),
            $request->idempotencyKey,
            $this->input->saveGlobal($request),
        );

        return $this->json($this->result->savedGlobal($output));
    }

    public function groupAction(GroupConditionsRequestDto $request): ControllerJson
    {
        $output = $this->conditions->getGroup($this->getAuthUserId(), $this->input->token($request->authorization), $request->groupId);

        return $this->json($this->result->group($output));
    }

    public function saveGroupAction(SaveGroupConditionsRequestDto $request): ControllerJson
    {
        $output = $this->conditions->saveGroup(
            $this->getAuthUserId(),
            $this->input->token($request->authorization),
            $request->groupId,
            $request->idempotencyKey,
            $this->input->saveGroup($request),
        );

        return $this->json($this->result->savedGroup($output));
    }
}
