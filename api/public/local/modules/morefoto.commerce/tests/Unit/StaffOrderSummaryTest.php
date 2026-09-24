<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Application\Order\Contract\OrderStaffAccessInterface;
use Morefoto\Commerce\Application\Order\Dto\OrderStaffScopeOutputDto;
use Morefoto\Commerce\Application\Order\Dto\SearchOrdersInputDto;
use Morefoto\Commerce\Application\Order\Mapper\OrderRowMapper;
use Morefoto\Commerce\Application\Order\UseCase\SearchStaffOrdersUseCase;
use Morefoto\Commerce\Domain\Order\Repository\OrderRepository;
use Morefoto\Commerce\Domain\Order\Service\OrderCalendarPolicy;
use Morefoto\Commerce\Domain\Order\ValueObject\OrderSearchCriteria;
use Morefoto\Commerce\Presentation\Order\OrderResultMapper;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class StaffOrderSummaryTest extends TestCase
{
    public function testProductionSplitKeepsTheScopeAndOtherFiltersButNotTheProductionFilter(): void
    {
        $access = $this->createStub(OrderStaffAccessInterface::class);
        $access->method('scope')->willReturn(new OrderStaffScopeOutputDto('curator', 2, [3]));
        $counts = ['not-started' => 2, 'queued' => 1, 'printing' => 0, 'ready' => 0, 'delivered' => 0];
        $orders = $this->createMock(OrderRepository::class);
        $orders->method('count')->willReturnCallback(static function(OrderSearchCriteria $criteria): int {
            self::assertSame([[3], 'queued'], [$criteria->institutionScope, $criteria->productionStatus]);

            return 1;
        });
        $orders->method('page')->willReturn(new Result());
        $orders->expects(self::once())->method('productionCounts')->with(self::callback(
            static fn(OrderSearchCriteria $criteria): bool => [3] === $criteria->institutionScope
                && null === $criteria->productionStatus
                && 'unpaid' === $criteria->paymentStatus
                && 'Анна' === $criteria->query,
        ))->willReturn($counts);
        $calendar = new OrderCalendarPolicy();

        $page = (new SearchStaffOrdersUseCase($access, $orders, new OrderRowMapper($calendar), $calendar))
            ->execute(7, new SearchOrdersInputDto('Анна', null, null, null, 'unpaid', 'queued', null, null, 1, 25))
        ;

        self::assertSame($counts, $page->byProductionStatus);
        self::assertSame(['total' => 3, 'byProductionStatus' => $counts], (new OrderResultMapper())->meta($page)['summary']);
    }
}
