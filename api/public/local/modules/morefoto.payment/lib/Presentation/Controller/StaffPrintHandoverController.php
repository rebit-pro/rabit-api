<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Controller;

use Morefoto\Payment\Application\Receipt\UseCase\GetPrintHandoversUseCase;
use Morefoto\Payment\Application\Receipt\UseCase\HandOverPrintUseCase;
use Morefoto\Payment\Presentation\Receipt\ReceiptInputMapper;
use Morefoto\Payment\Presentation\Receipt\ReceiptResultMapper;
use Morefoto\Payment\Presentation\Receipt\Request\Dto\HandOverPrintRequestDto;
use Morefoto\Payment\Presentation\Receipt\Request\Dto\PrintHandoversRequestDto;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;

final class StaffPrintHandoverController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly GetPrintHandoversUseCase $handovers,
        private readonly HandOverPrintUseCase $handOver,
        private readonly ReceiptInputMapper $input,
        private readonly ReceiptResultMapper $result,
    ) {
        parent::__construct();
    }

    public function handoversAction(PrintHandoversRequestDto $request): ControllerJson
    {
        return $this->json($this->result->handovers($this->handovers->execute($this->getAuthUserId(), $request->groupId)));
    }

    public function handOverAction(HandOverPrintRequestDto $request): ControllerJson
    {
        return $this->json($this->result->handovers($this->handOver->execute($this->getAuthUserId(), $this->input->handOver($request))));
    }
}
