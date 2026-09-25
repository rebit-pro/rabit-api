<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Mapper;

use Morefoto\Commerce\Application\Order\Dto\OrderBuyerOutputDto;
use Morefoto\Commerce\Application\Order\Dto\OrderOutputDto;
use Morefoto\Commerce\Domain\Order\Service\OrderCalendarPolicy;

/**
 * Собирает проекцию заказа из строк БД; строки заказа сохраняют порядок и постоянные UUID.
 *
 * @phpstan-import-type OrderQuoteLine from OrderOutputDto
 */
final readonly class OrderRowMapper
{
    public function __construct(private OrderCalendarPolicy $calendar) {}

    /**
     * @param array<string, mixed>       $row
     * @param list<array<string, mixed>> $lines
     */
    public function order(array $row, array $lines): OrderOutputDto
    {
        $quoteLines = [];
        foreach ($lines as $line) {
            $quoteLines[] = $this->line($line);
        }
        $gifts = json_decode((string)$row['GIFTS'], true, 4, JSON_THROW_ON_ERROR);

        return new OrderOutputDto(
            id: (string)$row['PUBLIC_ID'],
            number: (string)$row['NUMBER'],
            institutionId: (string)$row['INSTITUTION_PUBLIC_ID'],
            institutionName: (string)$row['INSTITUTION_NAME'],
            shootId: (string)$row['SHOOT_PUBLIC_ID'],
            shootName: (string)$row['SHOOT_NAME'],
            groupId: (string)$row['GROUP_PUBLIC_ID'],
            groupName: (string)$row['GROUP_NAME'],
            audience: (string)$row['AUDIENCE'],
            createdAt: $this->calendar->display(new \DateTimeImmutable((string)$row['CREATED_AT'], new \DateTimeZone('UTC'))),
            buyer: new OrderBuyerOutputDto(
                (string)$row['BUYER_NAME'],
                (string)$row['BUYER_PHONE'],
                (string)$row['BUYER_EMAIL'],
                (string)$row['BUYER_COMMENT'],
                null === $row['RECEIPT_CHANNEL'] ? null : (string)$row['RECEIPT_CHANNEL'],
            ),
            quote: [
                'lines' => $quoteLines,
                'total' => (int)$row['TOTAL'],
                'subtotal' => (int)$row['SUBTOTAL'],
                'discount' => (int)$row['DISCOUNT'],
                'giftSaving' => (int)$row['GIFT_SAVING'],
                'gifts' => array_values(array_map('strval', is_array($gifts) ? $gifts : [])),
                'count' => (int)$row['ITEM_COUNT'],
                'invalid' => [],
                'revision' => (int)$row['CATALOG_REVISION'],
                'conditionsRevision' => (int)$row['CONDITIONS_REVISION'],
            ],
            paymentStatus: (string)$row['PAYMENT_STATUS'],
            productionStatus: (string)$row['PRODUCTION_STATUS'],
            version: (string)$row['VERSION'],
            paidAt: null === ($row['PAID_AT'] ?? null) ? null : $this->calendar->display(new \DateTimeImmutable((string)$row['PAID_AT'], new \DateTimeZone('UTC'))),
            latePayment: 1 === (int)($row['LATE_PAYMENT'] ?? 0),
        );
    }

    /**
     * @param array<string, mixed> $line
     *
     * @return OrderQuoteLine
     */
    private function line(array $line): array
    {
        $photoId = null === $line['PHOTO_PUBLIC_ID'] ? null : (string)$line['PHOTO_PUBLIC_ID'];

        return [
            'id' => (string)$line['PUBLIC_ID'],
            'assignmentId' => (string)$line['ASSIGNMENT_PUBLIC_ID'],
            'childCode' => (string)$line['CHILD_CODE'],
            'photoId' => $photoId,
            'productId' => (string)$line['PRODUCT_PUBLIC_ID'],
            'quantity' => (int)$line['QUANTITY'],
            'product' => [
                'id' => (string)$line['PRODUCT_PUBLIC_ID'],
                'name' => (string)$line['PRODUCT_NAME'],
                'description' => (string)$line['PRODUCT_DESCRIPTION'],
                'kind' => (string)$line['PRODUCT_KIND'],
                'price' => (int)$line['PRODUCT_PRICE'],
                'printCount' => (int)$line['PRINT_COUNT'],
                'format' => (string)$line['PRODUCT_FORMAT'],
                'unit' => (string)$line['PRODUCT_UNIT'],
                'staffDiscount' => 1 === (int)$line['STAFF_DISCOUNT'],
                'active' => true,
            ],
            'photo' => null === $photoId ? null : [
                'id' => $photoId,
                'assignmentId' => (string)$line['ASSIGNMENT_PUBLIC_ID'],
                'code' => (string)$line['PHOTO_CODE'],
                'width' => (int)$line['PHOTO_WIDTH'],
                'height' => (int)$line['PHOTO_HEIGHT'],
            ],
            'unitPrice' => (int)$line['UNIT_PRICE'],
            'total' => (int)$line['TOTAL'],
            'discount' => (int)$line['DISCOUNT'],
            'coveredByGift' => 1 === (int)$line['COVERED_BY_GIFT'],
        ];
    }
}
