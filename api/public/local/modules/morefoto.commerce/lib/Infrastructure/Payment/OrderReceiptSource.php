<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Infrastructure\Payment;

use Morefoto\Commerce\Domain\Order\Repository\OrderRepository;
use Rebit\Share\Contracts\Commerce\Dto\OrderReceiptLineOutputDto;
use Rebit\Share\Contracts\Commerce\Dto\OrderReceiptSourceOutputDto;
use Rebit\Share\Contracts\Commerce\OrderReceiptSourceInterface;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class OrderReceiptSource implements OrderReceiptSourceInterface
{
    public function __construct(private OrderRepository $orders) {}

    public function find(int $orderId): OrderReceiptSourceOutputDto
    {
        /** @var array{BUYER_EMAIL: string}|false $order */
        $order = $this->orders->find($orderId)->fetch();
        if (false === $order) {
            throw new HttpException('ORDER_NOT_FOUND', 404);
        }
        $result = $this->orders->lines([$orderId]);
        $lines = [];
        while (false !== ($row = $result->fetch())) {
            $lines[] = new OrderReceiptLineOutputDto(
                kind: (string)$row['PRODUCT_KIND'],
                name: (string)$row['PRODUCT_NAME'],
                quantity: (int)$row['QUANTITY'],
                unitPrice: (int)$row['UNIT_PRICE'],
                total: (int)$row['TOTAL'],
            );
        }

        return new OrderReceiptSourceOutputDto((string)$order['BUYER_EMAIL'], $lines);
    }
}
