<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Dto;

final readonly class ProviderPaymentOutputDto
{
    /**
     * @param string      $status       статус провайдера: pending, waiting_for_capture, succeeded, canceled
     * @param int         $amount       сумма в копейках
     * @param null|string $attemptId    ID попытки из метаданных платежа
     * @param null|string $paidAt       момент списания UTC `Y-m-d H:i:s`
     * @param null|int    $incomeAmount сумма к зачислению после удержания провайдера, в копейках
     */
    public function __construct(
        public string $id,
        public string $status,
        public int $amount,
        public string $currency,
        public string $shopId,
        public ?string $attemptId,
        public ?string $confirmationUrl,
        public ?string $paidAt,
        public ?int $incomeAmount,
        public ?string $cancelReason,
    ) {}
}
