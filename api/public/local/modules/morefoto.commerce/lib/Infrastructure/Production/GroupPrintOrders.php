<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Infrastructure\Production;

use Morefoto\Commerce\Domain\Order\Repository\OrderRepository;
use Rebit\Share\Contracts\Commerce\Dto\PaidPrintLineOutputDto;
use Rebit\Share\Contracts\Commerce\GroupPrintOrdersInterface;

final readonly class GroupPrintOrders implements GroupPrintOrdersInterface
{
    public function __construct(private OrderRepository $orders) {}

    public function paidPrintLines(string $groupId, \DateTimeImmutable $deadline): array
    {
        $result = $this->orders->paidPrintLines($groupId, $deadline->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
        $lines = [];
        while (false !== ($row = $result->fetch())) {
            $lines[] = new PaidPrintLineOutputDto(
                lineId: (string)$row['PUBLIC_ID'],
                orderId: (string)$row['ORDER_PUBLIC_ID'],
                orderNumber: (string)$row['NUMBER'],
                paidAt: (string)$row['PAID_AT'],
                audience: (string)$row['AUDIENCE'],
                childCode: (string)$row['CHILD_CODE'],
                photoId: (string)$row['PHOTO_PUBLIC_ID'],
                photoCode: (string)$row['PHOTO_CODE'],
                productId: (string)$row['PRODUCT_PUBLIC_ID'],
                productName: (string)$row['PRODUCT_NAME'],
                productFormat: (string)$row['PRODUCT_FORMAT'],
                printCount: (int)$row['PRINT_COUNT'],
                quantity: (int)$row['QUANTITY'],
            );
        }

        return $lines;
    }

    public function waitingPrintOrders(string $groupId): int
    {
        return $this->orders->waitingPrintOrders($groupId);
    }
}
