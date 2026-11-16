<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Mapper;

use Morefoto\Commerce\Application\Sales\Dto\WorksheetPurchaseOutputDto;
use Morefoto\Commerce\Domain\Sales\Repository\SalesReportRepository;

/** @phpstan-import-type WorksheetRow from SalesReportRepository */
final readonly class WorksheetPurchaseMapper
{
    /** @param WorksheetRow $row */
    public function row(array $row): WorksheetPurchaseOutputDto
    {
        return new WorksheetPurchaseOutputDto(
            orderId: $row['ORDER_PUBLIC_ID'],
            number: (string)$row['NUMBER'],
            groupId: $row['GROUP_PUBLIC_ID'],
            groupName: $row['GROUP_NAME'],
            institutionName: $row['INSTITUTION_NAME'],
            shootName: $row['SHOOT_NAME'],
            shootId: (int)$row['SHOOT_ID'],
            childId: (int)$row['CHILD_ID'],
            childPublicId: $row['CHILD_PUBLIC_ID'],
            childCode: $row['CHILD_CODE'],
            firstName: $row['CHILD_FIRST_NAME'],
            lastName: $row['CHILD_LAST_NAME'],
            paymentStatus: $row['PAYMENT_STATUS'],
            latePayment: 1 === (int)$row['LATE_PAYMENT'],
            paid: 1 === (int)$row['IS_PAID'],
            orderTotal: (int)$row['ORDER_TOTAL'],
            gifts: $row['GIFTS'],
            lineId: $row['LINE_ID'],
            kind: $row['PRODUCT_KIND'],
            productId: $row['PRODUCT_PUBLIC_ID'],
            productName: $row['PRODUCT_NAME'],
            format: $row['PRODUCT_FORMAT'],
            photoId: $row['PHOTO_PUBLIC_ID'],
            quantity: (int)$row['QUANTITY'],
            printCount: (int)$row['PRINT_COUNT'],
            unitPrice: (int)$row['UNIT_PRICE'],
            discount: (int)$row['LINE_DISCOUNT'],
            total: (int)$row['LINE_TOTAL'],
            coveredByGift: 1 === (int)$row['COVERED_BY_GIFT'],
        );
    }
}
