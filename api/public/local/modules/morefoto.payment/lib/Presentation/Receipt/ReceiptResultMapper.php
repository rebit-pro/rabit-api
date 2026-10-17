<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Receipt;

use Morefoto\Payment\Application\Receipt\Dto\OrderReceiptsOutputDto;
use Morefoto\Payment\Application\Receipt\Dto\PrintHandoverOrderOutputDto;
use Morefoto\Payment\Application\Receipt\Dto\PrintHandoversOutputDto;
use Morefoto\Payment\Application\Receipt\Dto\ReceiptItemOutputDto;
use Morefoto\Payment\Application\Receipt\Dto\ReceiptOutputDto;
use Morefoto\Payment\Presentation\Receipt\Result\Dto\OrderReceiptsResultDto;
use Morefoto\Payment\Presentation\Receipt\Result\Dto\PrintHandoverOrderResultDto;
use Morefoto\Payment\Presentation\Receipt\Result\Dto\PrintHandoversResultDto;
use Morefoto\Payment\Presentation\Receipt\Result\Dto\ReceiptItemResultDto;
use Morefoto\Payment\Presentation\Receipt\Result\Dto\ReceiptResultDto;

final readonly class ReceiptResultMapper
{
    public function receipts(OrderReceiptsOutputDto $output): OrderReceiptsResultDto
    {
        return new OrderReceiptsResultDto($output->enabled, $output->channel, array_map($this->receipt(...), $output->receipts));
    }

    public function receipt(ReceiptOutputDto $receipt): ReceiptResultDto
    {
        return new ReceiptResultDto(
            id: $receipt->id,
            kind: $receipt->kind,
            status: $receipt->status,
            amount: $receipt->amount,
            items: array_map(static fn(ReceiptItemOutputDto $item): ReceiptItemResultDto => new ReceiptItemResultDto($item->description, $item->quantity, $item->amount, $item->paymentMode), $receipt->items),
            createdAt: $receipt->createdAt,
            registeredAt: $receipt->registeredAt,
            fiscalDocumentNumber: $receipt->fiscalDocumentNumber,
            fiscalStorageNumber: $receipt->fiscalStorageNumber,
            fiscalAttribute: $receipt->fiscalAttribute,
            handedAt: $receipt->handedAt,
        );
    }

    public function handovers(PrintHandoversOutputDto $output): PrintHandoversResultDto
    {
        return new PrintHandoversResultDto(
            groupId: $output->groupId,
            groupName: $output->groupName,
            enabled: $output->enabled,
            orders: array_map(static fn(PrintHandoverOrderOutputDto $order): PrintHandoverOrderResultDto => new PrintHandoverOrderResultDto(
                orderId: $order->orderId,
                orderNumber: $order->orderNumber,
                childCodes: $order->childCodes,
                prints: $order->prints,
                paidAt: $order->paidAt,
                state: $order->state,
                handedAt: $order->handedAt,
                receiptStatus: $order->receiptStatus,
                reason: $order->reason,
            ), $output->orders),
            summary: ['waiting' => $output->waiting, 'handed' => $output->handed, 'blocked' => $output->blocked],
        );
    }
}
