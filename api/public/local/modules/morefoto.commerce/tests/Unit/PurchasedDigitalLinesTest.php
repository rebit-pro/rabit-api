<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Domain\Order\Repository\OrderRepository;
use Morefoto\Commerce\Infrastructure\Files\PurchasedDigitalLines;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class PurchasedDigitalLinesTest extends TestCase
{
    public function testProviderPassesUtcCutoffAndNativeInstitutionAndMapsOnlyItsPublicProjection(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects(self::exactly(2))->method('fetch')->willReturnOnConsecutiveCalls([
            'ORDER_PUBLIC_ID' => 'order', 'NUMBER' => 'MF-000001', 'PUBLIC_ID' => 'line', 'GROUP_PUBLIC_ID' => 'group',
            'SHOOT_ID' => '10', 'CHILD_ID' => '11', 'CHILD_CODE' => 'A', 'PRODUCT_KIND' => 'bundle', 'PHOTO_PUBLIC_ID' => null,
        ], false);
        $repository = $this->createMock(OrderRepository::class);
        $repository->expects(self::once())->method('purchasedDigitalLines')->with(7, '2026-11-17 09:00:00', 50_001)->willReturn($result);
        $lines = (new PurchasedDigitalLines($repository))->forInstitution(7, new \DateTimeImmutable('2026-11-17T12:00:00+03:00'));

        self::assertCount(1, $lines);
        self::assertSame(['order', 'MF-000001', 'line', 'group', 10, 11, 'A', 'bundle', null], array_values(get_object_vars($lines[0])));
    }
}
