<?php

declare(strict_types=1);

namespace Morefoto\Production\Presentation\Controller;

use Morefoto\Production\Application\Archive\UseCase\CreateProductionVersionUseCase;
use Morefoto\Production\Application\Archive\UseCase\ExportProductionVersionUseCase;
use Morefoto\Production\Application\Archive\UseCase\GetGroupProductionUseCase;
use Morefoto\Production\Application\Archive\UseCase\GetProductionVersionUseCase;
use Morefoto\Production\Application\Archive\UseCase\ListProductionGroupsUseCase;
use Morefoto\Production\Application\Archive\UseCase\RenewProductionVersionUseCase;
use Morefoto\Production\Presentation\Archive\ProductionInputMapper;
use Morefoto\Production\Presentation\Archive\ProductionResultMapper;
use Morefoto\Production\Presentation\Archive\Request\Dto\CreateVersionRequestDto;
use Morefoto\Production\Presentation\Archive\Request\Dto\GroupProductionRequestDto;
use Morefoto\Production\Presentation\Archive\Request\Dto\ProductionGroupsRequestDto;
use Morefoto\Production\Presentation\Archive\Request\Dto\RenewVersionRequestDto;
use Morefoto\Production\Presentation\Archive\Request\Dto\VersionRequestDto;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;
use Rebit\Share\Infrastructure\Controller\Responses\TextFileResponse;

final class ProductionController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly ListProductionGroupsUseCase $groups,
        private readonly GetGroupProductionUseCase $group,
        private readonly CreateProductionVersionUseCase $create,
        private readonly GetProductionVersionUseCase $version,
        private readonly ExportProductionVersionUseCase $export,
        private readonly RenewProductionVersionUseCase $renew,
        private readonly ProductionInputMapper $input,
        private readonly ProductionResultMapper $result,
    ) {
        parent::__construct();
    }

    public function groupsAction(ProductionGroupsRequestDto $request): ControllerJson
    {
        return $this->json($this->result->groups($this->groups->execute($this->getAuthUserId(), $this->input->groupIds($request))));
    }

    public function groupAction(GroupProductionRequestDto $request): ControllerJson
    {
        return $this->json($this->result->group($this->group->execute($this->getAuthUserId(), $request->groupId)));
    }

    public function createAction(CreateVersionRequestDto $request): ControllerJson
    {
        $version = $this->result->version($this->create->execute($this->getAuthUserId(), $request->groupId, $this->input->key($request)));

        return $this->createdJson($version, '/api/v1/production/versions/' . $version->id);
    }

    public function versionAction(VersionRequestDto $request): ControllerJson
    {
        return $this->json($this->result->version($this->version->execute($this->getAuthUserId(), $request->versionId)));
    }

    public function exportAction(VersionRequestDto $request): TextFileResponse
    {
        return $this->textFile($this->export->execute($this->getAuthUserId(), $request->versionId));
    }

    public function renewalAction(RenewVersionRequestDto $request): ControllerJson
    {
        return $this->json($this->result->version($this->renew->execute($this->getAuthUserId(), $request->versionId, $this->input->key($request))));
    }
}
