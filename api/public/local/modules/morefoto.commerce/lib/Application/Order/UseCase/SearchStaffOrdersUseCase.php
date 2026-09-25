<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\UseCase;

use Morefoto\Commerce\Application\Order\Contract\OrderStaffAccessInterface;
use Morefoto\Commerce\Application\Order\Dto\SearchOrdersInputDto;
use Morefoto\Commerce\Application\Order\Dto\StaffOrderPageOutputDto;
use Morefoto\Commerce\Application\Order\Mapper\OrderRowMapper;
use Morefoto\Commerce\Domain\Order\Repository\OrderRepository;
use Morefoto\Commerce\Domain\Order\Service\OrderCalendarPolicy;
use Morefoto\Commerce\Domain\Order\ValueObject\OrderSearchCriteria;

/** Ищет заказы для организатора и куратора строго в их области с фильтрами и пагинацией на стороне БД и считает,
 * сколько найденных заказов на каждом этапе изготовления. Всё собирается четырьмя запросами без N+1, а перед выдачей
 * права сотрудника сверяются повторно.
 */
final readonly class SearchStaffOrdersUseCase
{
    public function __construct(
        private OrderStaffAccessInterface $access,
        private OrderRepository $orders,
        private OrderRowMapper $mapper,
        private OrderCalendarPolicy $calendar,
    ) {}

    public function execute(int $actorId, SearchOrdersInputDto $input): StaffOrderPageOutputDto
    {
        $scope = $this->access->scope($actorId);
        $criteria = new OrderSearchCriteria(
            institutionScope: $scope->institutionIds,
            query: $input->query,
            institutionId: $input->institutionId,
            shootId: $input->shootId,
            groupId: $input->groupId,
            paymentStatus: $input->paymentStatus,
            productionStatus: $input->productionStatus,
            createdFrom: null === $input->dateFrom ? null : $this->calendar->dayStart($input->dateFrom),
            createdBefore: null === $input->dateTo ? null : $this->calendar->dayStart($input->dateTo, true),
            latePayment: $input->latePayment,
        );
        $total = $this->orders->count($criteria);
        // Production tiles filter the list themselves, so their counts ignore the production filter of the search.
        $byProductionStatus = $this->orders->productionCounts(new OrderSearchCriteria(
            institutionScope: $criteria->institutionScope,
            query: $criteria->query,
            institutionId: $criteria->institutionId,
            shootId: $criteria->shootId,
            groupId: $criteria->groupId,
            paymentStatus: $criteria->paymentStatus,
            createdFrom: $criteria->createdFrom,
            createdBefore: $criteria->createdBefore,
            latePayment: $criteria->latePayment,
        ));
        /** @var array<int, array<string, mixed>> $rows */
        $rows = [];
        $result = $this->orders->page($criteria, $input->pageSize, ($input->page - 1) * $input->pageSize);
        while (false !== ($row = $result->fetch())) {
            $rows[(int)$row['ID']] = $row;
        }
        /** @var array<int, list<array<string, mixed>>> $lines */
        $lines = [];
        if ([] !== $rows) {
            $result = $this->orders->lines(array_keys($rows));
            while (false !== ($line = $result->fetch())) {
                $lines[(int)$line['ORDER_ID']][] = $line;
            }
        }
        $items = [];
        foreach ($rows as $id => $row) {
            $items[] = $this->mapper->order($row, $lines[$id] ?? []);
        }
        $this->access->assertUnchanged($actorId, $scope);

        return new StaffOrderPageOutputDto($items, $input->page, $input->pageSize, $total, $byProductionStatus);
    }
}
