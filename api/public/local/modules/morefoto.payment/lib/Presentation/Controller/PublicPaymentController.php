<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Controller;

use Morefoto\Payment\Application\Payment\UseCase\GetPaymentAttemptUseCase;
use Morefoto\Payment\Application\Payment\UseCase\GetPaymentQuoteUseCase;
use Morefoto\Payment\Application\Payment\UseCase\StartPaymentAttemptUseCase;
use Morefoto\Payment\Presentation\Payment\PaymentInputMapper;
use Morefoto\Payment\Presentation\Payment\PaymentResultMapper;
use Morefoto\Payment\Presentation\Payment\Request\Dto\PaymentAttemptRequestDto;
use Morefoto\Payment\Presentation\Payment\Request\Dto\PaymentQuoteRequestDto;
use Morefoto\Payment\Presentation\Payment\Request\Dto\StartPaymentRequestDto;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\PrivateApiJsonController;

final class PublicPaymentController extends PrivateApiJsonController
{
    public function __construct(
        private readonly GetPaymentQuoteUseCase $quote,
        private readonly StartPaymentAttemptUseCase $start,
        private readonly GetPaymentAttemptUseCase $attempt,
        private readonly PaymentInputMapper $input,
        private readonly PaymentResultMapper $result,
    ) {
        parent::__construct();
    }

    public function quoteAction(PaymentQuoteRequestDto $request): ControllerJson
    {
        return $this->json($this->result->quote($this->quote->execute($request->orderKey)));
    }

    public function startAction(StartPaymentRequestDto $request): ControllerJson
    {
        $output = $this->start->execute($request->orderKey, $this->input->key($request), $this->input->start($request));

        return $this->json($this->result->attempt($output))->setStatus(self::HTTP_ACCEPTED_CODE);
    }

    public function attemptAction(PaymentAttemptRequestDto $request): ControllerJson
    {
        return $this->json($this->result->attempt($this->attempt->execute($request->orderKey, $request->attemptId)));
    }
}
