<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Infrastructure\Files;

use Morefoto\Commerce\Domain\Order\Repository\OrderRepository;
use Rebit\Share\Contracts\Commerce\Dto\PurchasedDigitalLineOutputDto;
use Rebit\Share\Contracts\Commerce\PurchasedDigitalLinesInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Проекция купленных электронных строк из неизменяемых покупок, без расширенных прав J4 и подарков. */
final readonly class PurchasedDigitalLines implements PurchasedDigitalLinesInterface
{
    public function __construct(private OrderRepository $orders) {}

    public function forInstitution(int $institutionId, \DateTimeImmutable $at): array
    {
        $result = $this->orders->purchasedDigitalLines($institutionId, $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), self::MAX_LINES + 1);
        $lines = [];
        while (false !== ($row = $result->fetch())) {
            /** @var array{ORDER_PUBLIC_ID: string, NUMBER: string, PUBLIC_ID: string, GROUP_PUBLIC_ID: string, SHOOT_ID: int|string, CHILD_ID: int|string, CHILD_CODE: string, PRODUCT_KIND: string, PHOTO_PUBLIC_ID: null|string} $row */
            if (self::MAX_LINES === count($lines)) {
                throw new HttpException('DIGITAL_ARCHIVE_TOO_LARGE', 413);
            }
            $lines[] = new PurchasedDigitalLineOutputDto(
                orderId: $row['ORDER_PUBLIC_ID'],
                orderNumber: $row['NUMBER'],
                lineId: $row['PUBLIC_ID'],
                groupId: $row['GROUP_PUBLIC_ID'],
                shootId: (int)$row['SHOOT_ID'],
                childId: (int)$row['CHILD_ID'],
                childCode: $row['CHILD_CODE'],
                kind: $row['PRODUCT_KIND'],
                photoId: $row['PHOTO_PUBLIC_ID'],
            );
        }

        return $lines;
    }
}
