<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Controller;

use Morefoto\Payment\Application\Payment\UseCase\AcceptPaymentNotificationUseCase;
use Morefoto\Payment\Presentation\Payment\PaymentInputMapper;
use Morefoto\Payment\Presentation\Payment\PaymentResultMapper;
use Morefoto\Payment\Presentation\Payment\Request\Dto\PaymentNotificationRequestDto;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\PrivateApiJsonController;

final class PaymentWebhookController extends PrivateApiJsonController
{
    public function __construct(
        private readonly AcceptPaymentNotificationUseCase $accept,
        private readonly PaymentInputMapper $input,
        private readonly PaymentResultMapper $result,
    ) {
        parent::__construct();
    }

    public function paymentsAction(PaymentNotificationRequestDto $request): ControllerJson
    {
        $this->accept->execute($this->input->notification($request));

        return $this->json($this->result->acknowledged());
    }
}
