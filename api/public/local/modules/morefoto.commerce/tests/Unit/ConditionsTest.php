<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Morefoto\Commerce\Domain\Catalog\Enum\ProductKind;
use Morefoto\Commerce\Domain\Conditions\Service\SalesPolicy;
use Morefoto\Commerce\Domain\Conditions\ValueObject\SaleItem;
use Morefoto\Commerce\Domain\Conditions\ValueObject\SalesProduct;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * @internal
 */
final class ConditionsTest extends TestCase
{
    private const string PRINT_ID = '11111111-1111-4111-8111-111111111111';
    private const string DIGITAL_ID = '22222222-2222-4222-8222-222222222222';
    private const string BUNDLE_ID = '33333333-3333-4333-8333-333333333333';

    public function testStaffDiscountRoundsFinalOddPriceHalfUp(): void
    {
        $policy = $this->policy(0, false, 101);
        $quote = $policy->quote([$this->item('print', 'child-a', 'shared-photo', self::PRINT_ID)], true);

        self::assertSame(101, $quote->subtotal);
        self::assertSame(50, $quote->staffDiscount);
        self::assertSame(51, $quote->items[0]->unitPrice);
        self::assertSame(51, $quote->total);
    }

    public function testGiftThresholdIsCalculatedPerChildAndSharedPhotoIsAllowed(): void
    {
        $policy = $this->policy(200, false, 100);
        $quote = $policy->quote([
            $this->item('a-print', 'child-a', 'shared-photo', self::PRINT_ID, 2),
            $this->item('b-print', 'child-b', 'shared-photo', self::PRINT_ID, 2),
            $this->item('a-bundle', 'child-a', null, self::BUNDLE_ID),
            $this->item('b-bundle', 'child-b', null, self::BUNDLE_ID),
        ], false);

        self::assertSame(['child-a' => self::BUNDLE_ID, 'child-b' => self::BUNDLE_ID], $quote->gifts);
        self::assertSame(400, $quote->total);
        self::assertSame(1000, $quote->giftSaving);
    }

    public function testGiftCoversOnlyBundleAndNeverStacksWithStaffDiscount(): void
    {
        $policy = $this->policy(100, true, 200);
        $quote = $policy->quote([
            $this->item('print', 'child-a', 'photo-a', self::PRINT_ID),
            $this->item('digital', 'child-a', 'photo-a', self::DIGITAL_ID),
            $this->item('bundle', 'child-a', null, self::BUNDLE_ID),
        ], true);

        self::assertSame(['child-a' => self::BUNDLE_ID], $quote->gifts);
        self::assertSame(250, $quote->staffDiscount);
        self::assertSame(500, $quote->giftSaving);
        self::assertSame(250, $quote->total);
        self::assertSame(150, $quote->items[1]->total);
        self::assertFalse($quote->items[1]->coveredByGift);
        self::assertSame(0, $quote->items[2]->staffDiscount);
        self::assertTrue($quote->items[2]->coveredByGift);
    }

    public function testStaffGiftThresholdUsesDiscountedPhysicalTotal(): void
    {
        $policy = $this->policy(150, true, 101);
        $below = $policy->quote([$this->item('print', 'child-a', 'photo-a', self::PRINT_ID, 2)], true);
        $eligible = $policy->quote([
            $this->item('print', 'child-a', 'photo-a', self::PRINT_ID, 3),
            $this->item('bundle', 'child-a', null, self::BUNDLE_ID),
        ], true);

        self::assertSame([], $below->gifts);
        self::assertSame(['child-a' => self::BUNDLE_ID], $eligible->gifts);
        self::assertSame(153, $eligible->total);
    }

    public function testDuplicateBundleAndUnavailableProductAreInvalidLines(): void
    {
        $policy = $this->policy(0, false, 100);
        $quote = $policy->quote([
            $this->item('first', 'child-a', null, self::BUNDLE_ID),
            $this->item('duplicate', 'child-a', null, self::BUNDLE_ID),
            $this->item('missing', 'child-a', 'photo-a', '44444444-4444-4444-8444-444444444444'),
        ], false);

        self::assertSame(['duplicate', 'missing'], $quote->invalidItemIds);
        self::assertCount(1, $quote->items);
    }

    private function policy(int $threshold, bool $giftForStaff, int $printPrice): SalesPolicy
    {
        return new SalesPolicy(7, 4, [
            new SalesProduct(self::PRINT_ID, ProductKind::PHYSICAL, $printPrice, true, true),
            new SalesProduct(self::DIGITAL_ID, ProductKind::DIGITAL, 300, true, true),
            new SalesProduct(self::BUNDLE_ID, ProductKind::BUNDLE, 500, true, true),
        ], $threshold, $giftForStaff);
    }

    private function item(string $id, string $childId, ?string $photoId, string $productId, int $quantity = 1): SaleItem
    {
        return new SaleItem($id, $childId, $photoId, $productId, $quantity);
    }
}
