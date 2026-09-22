<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Controller;

use Morefoto\Commerce\Application\Order\UseCase\GetStaffOrderUseCase;
use Morefoto\Commerce\Application\Order\UseCase\SearchStaffOrdersUseCase;
use Morefoto\Commerce\Presentation\Order\Dto\StaffOrderDetailRequestDto;
use Morefoto\Commerce\Presentation\Order\Dto\StaffOrderListRequestDto;
use Morefoto\Commerce\Presentation\Order\OrderInputMapper;
use Morefoto\Commerce\Presentation\Order\OrderResultMapper;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;

final class StaffOrderController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly SearchStaffOrdersUseCase $search,
        private readonly GetStaffOrderUseCase $detail,
        private readonly OrderInputMapper $input,
        private readonly OrderResultMapper $result,
    ) {
        parent::__construct();
    }

    public function listAction(StaffOrderListRequestDto $request): ControllerJson
    {
        $output = $this->search->execute($this->getAuthUserId(), $this->input->search($request));

        return $this->json($this->result->staffList($output), $this->result->meta($output));
    }

    public function detailAction(StaffOrderDetailRequestDto $request): ControllerJson
    {
        return $this->json($this->result->staffDetail($this->detail->execute($this->getAuthUserId(), $request->orderId)));
    }
}
