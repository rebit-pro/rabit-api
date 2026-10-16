<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Controller;

use Morefoto\Commerce\Application\Sales\UseCase\GetGroupSalesUseCase;
use Morefoto\Commerce\Presentation\Sales\Dto\GroupSalesRequestDto;
use Morefoto\Commerce\Presentation\Sales\SalesResultMapper;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;

final class GroupSalesController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly GetGroupSalesUseCase $sales,
        private readonly SalesResultMapper $result,
    ) {
        parent::__construct();
    }

    public function groupAction(GroupSalesRequestDto $request): ControllerJson
    {
        return $this->json($this->result->group($this->sales->execute($this->getAuthUserId(), $request->groupId)));
    }
}
