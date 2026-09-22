<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Mapper;

use Morefoto\Commerce\Application\Storefront\Dto\ValidatedQuoteOutputDto;
use Morefoto\Commerce\Domain\Order\Enum\PaymentStatusEnum;
use Morefoto\Commerce\Domain\Order\Enum\ProductionStatusEnum;
use Morefoto\Commerce\Domain\Order\Repository\OrderRepository;
use Morefoto\Commerce\Domain\Order\ValueObject\OrderBuyer;
use Rebit\Share\Contracts\Media\Dto\GalleryAssignmentOutputDto;
use Rebit\Share\Contracts\Organization\Dto\MediaScopeOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Превращает проверенный серверный расчёт в неизменяемые записи заказа и строк.
 *
 * @phpstan-import-type OrderRecord from OrderRepository
 * @phpstan-import-type OrderLineRecord from OrderRepository
 */
final readonly class OrderRecordMapper
{
    /**
     * @param list<string> $lineIds UUID строк в порядке строк расчёта
     *
     * @return array{order: OrderRecord, lines: list<OrderLineRecord>}
     */
    public function records(
        ValidatedQuoteOutputDto $validated,
        OrderBuyer $buyer,
        MediaScopeOutputDto $scope,
        string $orderId,
        array $lineIds,
        string $galleryHash,
        string $quoteHash,
        \DateTimeImmutable $now,
    ): array {
        $quote = $validated->quote->quote;
        $group = $validated->gallery->group;
        if (null === $scope->institutionPublicId || count($lineIds) !== count($quote['lines']) || [] === $quote['lines']) {
            throw new HttpException('INVALID_CART', 422);
        }
        /** @var array<string, GalleryAssignmentOutputDto> $assignments */
        $assignments = [];
        foreach ($validated->gallery->assignments as $assignment) {
            $assignments[$assignment->assignmentId] = $assignment;
        }
        $lines = [];
        foreach ($quote['lines'] as $index => $line) {
            $assignment = $assignments[$line['assignmentId']] ?? null;
            if (null === $assignment) {
                throw new HttpException('INVALID_CART', 422);
            }
            $lines[] = [
                'PUBLIC_ID' => $lineIds[$index],
                'LINE_NO' => $index + 1,
                'ASSIGNMENT_PUBLIC_ID' => $line['assignmentId'],
                'CHILD_ID' => $assignment->nativeChildId,
                'CHILD_PUBLIC_ID' => $assignment->childId,
                'CHILD_CODE' => $line['childCode'],
                'PHOTO_PUBLIC_ID' => null === $line['photo'] ? null : $line['photo']['id'],
                'PHOTO_CODE' => null === $line['photo'] ? null : $line['photo']['code'],
                'PHOTO_WIDTH' => null === $line['photo'] ? null : $line['photo']['width'],
                'PHOTO_HEIGHT' => null === $line['photo'] ? null : $line['photo']['height'],
                'PRODUCT_PUBLIC_ID' => $line['product']['id'],
                'PRODUCT_KIND' => $line['product']['kind'],
                'PRODUCT_NAME' => $line['product']['name'],
                'PRODUCT_DESCRIPTION' => $line['product']['description'],
                'PRODUCT_FORMAT' => $line['product']['format'],
                'PRODUCT_UNIT' => $line['product']['unit'],
                'PRODUCT_PRICE' => $line['product']['price'],
                'PRINT_COUNT' => $line['product']['printCount'],
                'STAFF_DISCOUNT' => $line['product']['staffDiscount'],
                'QUANTITY' => $line['quantity'],
                'UNIT_PRICE' => $line['unitPrice'],
                'DISCOUNT' => $line['discount'],
                'TOTAL' => $line['total'],
                'COVERED_BY_GIFT' => $line['coveredByGift'],
            ];
        }

        return ['order' => [
            'PUBLIC_ID' => $orderId,
            'GALLERY_HASH' => $galleryHash,
            'QUOTE_HASH' => $quoteHash,
            'INSTITUTION_ID' => $scope->institutionId,
            'INSTITUTION_PUBLIC_ID' => $scope->institutionPublicId,
            'SHOOT_ID' => $scope->shootId,
            'SHOOT_PUBLIC_ID' => $scope->shootPublicId,
            'GROUP_ID' => $group->id,
            'GROUP_PUBLIC_ID' => $group->publicId,
            'AUDIENCE' => $group->kind,
            'INSTITUTION_NAME' => $group->institutionName,
            'SHOOT_NAME' => $group->shootName,
            'GROUP_NAME' => $group->groupName,
            'BUYER_NAME' => $buyer->name,
            'BUYER_PHONE' => $buyer->phone,
            'BUYER_EMAIL' => $buyer->email,
            'BUYER_COMMENT' => $buyer->comment,
            'RECEIPT_CHANNEL' => $buyer->receiptChannel,
            'SUBTOTAL' => $quote['subtotal'],
            'DISCOUNT' => $quote['discount'],
            'GIFT_SAVING' => $quote['giftSaving'],
            'TOTAL' => $quote['total'],
            'ITEM_COUNT' => $quote['count'],
            'GIFTS' => json_encode($quote['gifts'], JSON_THROW_ON_ERROR),
            'CATALOG_REVISION' => $quote['revision'],
            'CONDITIONS_REVISION' => $quote['conditionsRevision'],
            'PAYMENT_STATUS' => PaymentStatusEnum::UNPAID->value,
            'PRODUCTION_STATUS' => ProductionStatusEnum::NOT_STARTED->value,
            'CREATED_AT' => $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ], 'lines' => $lines];
    }
}
