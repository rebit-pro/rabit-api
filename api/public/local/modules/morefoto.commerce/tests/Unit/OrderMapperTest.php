<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Morefoto\Commerce\Application\Order\Mapper\OrderRecordMapper;
use Morefoto\Commerce\Application\Order\Mapper\OrderRowMapper;
use Morefoto\Commerce\Application\Storefront\Dto\QuoteOutputDto;
use Morefoto\Commerce\Application\Storefront\Dto\ValidatedQuoteOutputDto;
use Morefoto\Commerce\Domain\Order\Service\OrderCalendarPolicy;
use Morefoto\Commerce\Domain\Order\ValueObject\OrderBuyer;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Media\Dto\GalleryAccessOutputDto;
use Rebit\Share\Contracts\Media\Dto\GalleryAssignmentOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GalleryGroupOutputDto;
use Rebit\Share\Contracts\Organization\Dto\MediaScopeOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class OrderMapperTest extends TestCase
{
    private const string ASSIGNMENT = '11111111-1111-4111-8111-111111111111';
    private const string PHOTO = '33333333-3333-4333-8333-333333333333';

    public function testSnapshotKeepsServerPricesChildAndStableLineIds(): void
    {
        $records = (new OrderRecordMapper())->records(
            $this->validated(),
            new OrderBuyer('Анна', '+79001234567', 'buyer@example.test', '', null),
            new MediaScopeOutputDto(5, 2, 'shoot-id', 1, 'group-id', false, 'institution-id', 'regular'),
            'order-id',
            ['line-1', 'line-2'],
            str_repeat('a', 64),
            str_repeat('c', 64),
            new \DateTimeImmutable('2026-09-22T13:00:00+03:00'),
        );

        self::assertSame('2026-09-22 10:00:00', $records['order']['CREATED_AT']);
        self::assertSame(5, $records['order']['INSTITUTION_ID']);
        self::assertSame('unpaid', $records['order']['PAYMENT_STATUS']);
        self::assertSame('["A"]', $records['order']['GIFTS']);
        self::assertSame(['line-1', 'line-2'], array_column($records['lines'], 'PUBLIC_ID'));
        self::assertSame([12, 12], array_column($records['lines'], 'CHILD_ID'));
        self::assertNull($records['lines'][1]['PHOTO_PUBLIC_ID']);
        self::assertSame('A003', $records['lines'][0]['PHOTO_CODE']);
    }

    public function testUnknownAssignmentCannotEnterSnapshot(): void
    {
        $validated = $this->validated();
        $quote = $validated->quote->quote;
        $quote['lines'][0]['assignmentId'] = '99999999-9999-4999-8999-999999999999';
        $this->expectException(HttpException::class);
        (new OrderRecordMapper())->records(
            new ValidatedQuoteOutputDto(new QuoteOutputDto($quote, str_repeat('c', 64), 'x'), $validated->gallery),
            new OrderBuyer('Анна', '+79001234567', 'buyer@example.test', '', null),
            new MediaScopeOutputDto(5, 2, 'shoot-id', 1, 'group-id', false, 'institution-id', 'regular'),
            'order-id',
            ['line-1', 'line-2'],
            str_repeat('a', 64),
            str_repeat('c', 64),
            new \DateTimeImmutable(),
        );
    }

    public function testRowsBecomeBuyerFacingOrderWithoutInternalIds(): void
    {
        $order = (new OrderRowMapper(new OrderCalendarPolicy()))->order(self::row(), [self::line()]);

        self::assertSame('MF-000007', $order->number);
        self::assertSame('2026-09-22T13:00:00+03:00', $order->createdAt);
        self::assertSame(['A'], $order->quote['gifts']);
        self::assertSame('line-1', $order->quote['lines'][0]['id']);
        self::assertSame(['id' => self::PHOTO, 'assignmentId' => self::ASSIGNMENT, 'code' => 'A003', 'width' => 1200, 'height' => 800], $order->quote['lines'][0]['photo']);
        self::assertTrue($order->quote['lines'][0]['product']['staffDiscount']);
        self::assertSame('1', $order->version);
    }

    /** @return array<string, mixed> */
    public static function row(): array
    {
        return [
            'ID' => '7', 'PUBLIC_ID' => 'order-id', 'NUMBER' => 'MF-000007', 'INSTITUTION_ID' => '5', 'INSTITUTION_PUBLIC_ID' => 'institution-id',
            'SHOOT_ID' => '2', 'SHOOT_PUBLIC_ID' => 'shoot-id', 'GROUP_ID' => '1', 'GROUP_PUBLIC_ID' => 'group-id', 'AUDIENCE' => 'regular',
            'INSTITUTION_NAME' => 'Institution', 'SHOOT_NAME' => 'Shoot', 'GROUP_NAME' => 'Group', 'BUYER_NAME' => 'Анна',
            'BUYER_PHONE' => '+79001234567', 'BUYER_EMAIL' => 'buyer@example.test', 'BUYER_COMMENT' => '', 'RECEIPT_CHANNEL' => null,
            'SUBTOTAL' => '50000', 'DISCOUNT' => '0', 'GIFT_SAVING' => '25000', 'TOTAL' => '25000', 'ITEM_COUNT' => '2', 'GIFTS' => '["A"]',
            'CATALOG_REVISION' => '3', 'CONDITIONS_REVISION' => '4', 'PAYMENT_STATUS' => 'unpaid', 'PRODUCTION_STATUS' => 'not-started',
            'VERSION' => '1', 'CREATED_AT' => '2026-09-22 10:00:00',
        ];
    }

    /** @return array<string, mixed> */
    public static function line(): array
    {
        return [
            'ORDER_ID' => '7', 'PUBLIC_ID' => 'line-1', 'LINE_NO' => '1', 'ASSIGNMENT_PUBLIC_ID' => self::ASSIGNMENT, 'CHILD_ID' => '12',
            'CHILD_PUBLIC_ID' => 'child-id', 'CHILD_CODE' => 'A', 'PHOTO_PUBLIC_ID' => self::PHOTO, 'PHOTO_CODE' => 'A003', 'PHOTO_WIDTH' => '1200',
            'PHOTO_HEIGHT' => '800', 'PRODUCT_PUBLIC_ID' => 'product-id', 'PRODUCT_KIND' => 'physical', 'PRODUCT_NAME' => 'Фото',
            'PRODUCT_DESCRIPTION' => '', 'PRODUCT_FORMAT' => '10x15', 'PRODUCT_UNIT' => 'шт', 'PRODUCT_PRICE' => '25000', 'PRINT_COUNT' => '1',
            'STAFF_DISCOUNT' => '1', 'QUANTITY' => '1', 'UNIT_PRICE' => '25000', 'DISCOUNT' => '0', 'TOTAL' => '25000', 'COVERED_BY_GIFT' => '0',
        ];
    }

    private function validated(): ValidatedQuoteOutputDto
    {
        $product = static fn(string $kind, int $price): array => [
            'id' => 'product-' . $kind, 'name' => $kind, 'description' => '', 'kind' => $kind, 'price' => $price, 'printCount' => 1,
            'format' => '', 'unit' => '', 'staffDiscount' => false, 'active' => true,
        ];
        $quote = [
            'lines' => [
                [
                    'id' => self::ASSIGNMENT . ':product-physical', 'assignmentId' => self::ASSIGNMENT, 'childCode' => 'A', 'photoId' => self::PHOTO,
                    'productId' => 'product-physical', 'quantity' => 2, 'product' => $product('physical', 12500),
                    'photo' => ['id' => self::PHOTO, 'assignmentId' => self::ASSIGNMENT, 'code' => 'A003', 'width' => 1200, 'height' => 800],
                    'unitPrice' => 12500, 'total' => 25000, 'discount' => 0, 'coveredByGift' => false,
                ],
                [
                    'id' => 'child-id:product-bundle', 'assignmentId' => self::ASSIGNMENT, 'childCode' => 'A', 'photoId' => null,
                    'productId' => 'product-bundle', 'quantity' => 1, 'product' => $product('bundle', 25000), 'photo' => null,
                    'unitPrice' => 25000, 'total' => 0, 'discount' => 0, 'coveredByGift' => true,
                ],
            ],
            'total' => 25000, 'subtotal' => 50000, 'discount' => 0, 'giftSaving' => 25000, 'gifts' => ['A'], 'count' => 3, 'invalid' => [],
            'revision' => 3, 'conditionsRevision' => 4,
        ];
        $group = new GalleryGroupOutputDto(1, 2, 'group-id', 'Institution', 'Shoot', 'Group', 'regular', null, null, 1);
        $assignment = new GalleryAssignmentOutputDto(self::ASSIGNMENT, self::PHOTO, 'child-id', 12, 'A', 'A003', 1200, 800, 1);

        return new ValidatedQuoteOutputDto(new QuoteOutputDto($quote, str_repeat('c', 64), '2026-09-22T10:15:00+00:00'), new GalleryAccessOutputDto($group, 'open', 'now', 1, [$assignment]));
    }
}
