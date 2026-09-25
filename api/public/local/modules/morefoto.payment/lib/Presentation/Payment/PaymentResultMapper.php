<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Payment;

use Morefoto\Payment\Application\Payment\Dto\PaymentAttemptOutputDto;
use Morefoto\Payment\Application\Payment\Dto\PaymentCardOutputDto;
use Morefoto\Payment\Application\Payment\Dto\PaymentItemOutputDto;
use Morefoto\Payment\Application\Payment\Dto\PaymentPageOutputDto;
use Morefoto\Payment\Application\Payment\Dto\PaymentQuoteOutputDto;
use Morefoto\Payment\Presentation\Payment\Result\Dto\PaymentAcknowledgedResultDto;
use Morefoto\Payment\Presentation\Payment\Result\Dto\PaymentAttemptResultDto;
use Morefoto\Payment\Presentation\Payment\Result\Dto\PaymentCardResultDto;
use Morefoto\Payment\Presentation\Payment\Result\Dto\PaymentFactResultDto;
use Morefoto\Payment\Presentation\Payment\Result\Dto\PaymentItemResultDto;
use Morefoto\Payment\Presentation\Payment\Result\Dto\PaymentListResultDto;
use Morefoto\Payment\Presentation\Payment\Result\Dto\PaymentQuoteResultDto;

final readonly class PaymentResultMapper
{
    public function quote(PaymentQuoteOutputDto $output): PaymentQuoteResultDto
    {
        return new PaymentQuoteResultDto(
            quote: ['subtotal' => $output->subtotal, 'discount' => $output->discount, 'giftSaving' => $output->giftSaving, 'total' => $output->total, 'currency' => 'RUB'],
            quoteToken: $output->quoteToken,
            orderVersion: $output->orderVersion,
            canPay: $output->canPay,
            precedingAttemptId: $output->precedingAttemptId,
            activeAttemptId: $output->activeAttemptId,
            paymentMethods: $output->paymentMethods,
        );
    }

    public function attempt(PaymentAttemptOutputDto $output): PaymentAttemptResultDto
    {
        return new PaymentAttemptResultDto(
            id: $output->id,
            status: $output->status,
            amount: $output->amount,
            paymentMethod: $output->paymentMethod,
            orderVersion: $output->orderVersion,
            latePayment: $output->latePayment,
            redirectUrl: $output->redirectUrl,
            created: $output->created,
        );
    }

    public function acknowledged(): PaymentAcknowledgedResultDto
    {
        return new PaymentAcknowledgedResultDto(true);
    }

    public function list(PaymentPageOutputDto $output): PaymentListResultDto
    {
        return new PaymentListResultDto(array_map($this->item(...), $output->items));
    }

    /** @return array{page: int, pageSize: int, total: int, totalPages: int} */
    public function meta(PaymentPageOutputDto $output): array
    {
        return [
            'page' => $output->page,
            'pageSize' => $output->pageSize,
            'total' => $output->total,
            'totalPages' => (int)ceil($output->total / $output->pageSize),
        ];
    }

    public function card(PaymentCardOutputDto $output): PaymentCardResultDto
    {
        $facts = [];
        foreach ($output->facts as $fact) {
            $facts[] = new PaymentFactResultDto($fact->attemptId, $fact->amount, $fact->incomeAmount, $fact->paidAt, $fact->latePayment, $fact->confirmedBy);
        }

        return new PaymentCardResultDto(
            payment: $this->item($output->payment),
            providerPaymentId: $output->providerPaymentId,
            checkCount: $output->checkCount,
            lastCheckAt: $output->lastCheckAt,
            nextCheckAt: $output->nextCheckAt,
            attempts: array_map($this->item(...), $output->attempts),
            facts: $facts,
        );
    }

    private function item(PaymentItemOutputDto $item): PaymentItemResultDto
    {
        return new PaymentItemResultDto(
            id: $item->id,
            orderId: $item->orderId,
            orderNumber: $item->orderNumber,
            institutionName: $item->institutionName,
            groupName: $item->groupName,
            createdAt: $item->createdAt,
            amount: $item->amount,
            currency: $item->currency,
            paymentMethod: $item->paymentMethod,
            status: $item->status,
            paidAt: $item->paidAt,
            latePayment: $item->latePayment,
            incomeAmount: $item->incomeAmount,
            cancelReason: $item->cancelReason,
        );
    }
}
