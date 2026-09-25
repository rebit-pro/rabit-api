<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Controller;

use Morefoto\Payment\Application\Payment\UseCase\GetPaymentUseCase;
use Morefoto\Payment\Application\Payment\UseCase\ListPaymentsUseCase;
use Morefoto\Payment\Presentation\Payment\PaymentInputMapper;
use Morefoto\Payment\Presentation\Payment\PaymentResultMapper;
use Morefoto\Payment\Presentation\Payment\Request\Dto\PaymentDetailRequestDto;
use Morefoto\Payment\Presentation\Payment\Request\Dto\PaymentListRequestDto;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;

final class StaffPaymentController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly ListPaymentsUseCase $list,
        private readonly GetPaymentUseCase $detail,
        private readonly PaymentInputMapper $input,
        private readonly PaymentResultMapper $result,
    ) {
        parent::__construct();
    }

    public function listAction(PaymentListRequestDto $request): ControllerJson
    {
        $output = $this->list->execute($this->getAuthUserId(), $this->input->search($request));

        return $this->json($this->result->list($output), $this->result->meta($output));
    }

    public function detailAction(PaymentDetailRequestDto $request): ControllerJson
    {
        return $this->json($this->result->card($this->detail->execute($this->getAuthUserId(), $request->attemptId)));
    }
}
