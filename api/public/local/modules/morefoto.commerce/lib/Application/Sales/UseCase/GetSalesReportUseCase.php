<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\UseCase;

use Morefoto\Commerce\Application\Sales\Dto\ChildSalesOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\OrderNameSalesOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\ProductSalesOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportAreaOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportInputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportNodeOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportRowOutputDto;
use Morefoto\Commerce\Application\Sales\Enum\SalesReportLevelEnum;
use Morefoto\Commerce\Application\Sales\Mapper\SalesReportRowMapper;
use Morefoto\Commerce\Application\Sales\Service\SalesReportScope;
use Morefoto\Commerce\Domain\Sales\Enum\SalesBreakdownEnum;
use Morefoto\Commerce\Domain\Sales\Repository\SalesReportRepository;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Media\Dto\GroupChildOutputDto;
use Rebit\Share\Contracts\Media\GroupChildrenInterface;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;

/**
 * COM-15: отчёт продаж для воспитателя, руководителя, куратора и организатора в их области — по детям группы со
 * статусом оплаты или по группам, съёмкам и учреждениям, с итогом и печатью по форматам. Отчёт собирается тремя
 * агрегирующими чтениями заказов, чтением заказов группы и одним чтением детей Media, без запросов на строку.
 */
final readonly class GetSalesReportUseCase
{
    public function __construct(
        private SalesReportScope $scope,
        private SalesReportRepository $sales,
        private GroupChildrenInterface $media,
        private SalesReportRowMapper $mapper,
        private ClockInterface $clock,
    ) {}

    public function execute(int $actorId, SalesReportInputDto $input): SalesReportOutputDto
    {
        $scope = $this->scope->resolve($actorId, $input);
        $first = $scope->groups[0] ?? null;
        $children = [];
        $rows = [];
        $orders = [];
        if (SalesReportLevelEnum::GROUP === $scope->level && null !== $first) {
            $current = $this->media->children([$first->nativeId]);
            $children = $this->children($scope, $current, null !== $first->calendar->sentAt);
            $childCount = count($current);
            $result = $this->sales->orders($scope->area);
            while (false !== ($row = $result->fetch())) {
                $orders[] = new OrderNameSalesOutputDto(
                    (string)$row['PUBLIC_ID'],
                    (string)$row['NUMBER'],
                    null === $row['CHILD_FIRST_NAME'] ? null : (string)$row['CHILD_FIRST_NAME'],
                    null === $row['CHILD_LAST_NAME'] ? null : (string)$row['CHILD_LAST_NAME'],
                    (string)$row['PAYMENT_STATUS'],
                    1 === (int)$row['LATE_PAYMENT'],
                    (int)$row['TOTAL'],
                );
            }
        } else {
            $counts = $this->media->counts(array_map(static fn(GroupDirectoryItemOutputDto $group): int => $group->nativeId, $scope->groups));
            $rows = $this->rows($scope, $counts);
            $childCount = array_sum($counts);
        }
        $products = [];
        $result = $this->sales->products($scope->area);
        while (false !== ($row = $result->fetch())) {
            $products[] = new ProductSalesOutputDto((string)$row['PRODUCT_PUBLIC_ID'], (string)$row['NAME'], (int)$row['PRINTS']);
        }
        $nested = SalesReportLevelEnum::ALL !== $scope->level && null !== $first;

        return new SalesReportOutputDto(
            level: $scope->level,
            institution: $nested ? new SalesReportNodeOutputDto($first->institutionId, $first->institutionName) : null,
            shoot: $nested && SalesReportLevelEnum::INSTITUTION !== $scope->level ? new SalesReportNodeOutputDto($first->shootId, $first->shootName) : null,
            group: $nested && SalesReportLevelEnum::GROUP === $scope->level ? new SalesReportNodeOutputDto($first->id, $first->name) : null,
            totals: $this->mapper->totals($this->sales->aggregate($scope->area, null)->fetch() ?: null, $childCount),
            products: $products,
            rows: $rows,
            children: $children,
            generatedAt: $this->clock->now(),
            orders: $orders,
        );
    }

    /**
     * Текущие дети разметки и дети, переведённые после заказа (D3): их заказ остаётся в отчёте исходной группы под
     * кодом из снимка и без миниатюры.
     *
     * @param list<GroupChildOutputDto> $current
     *
     * @return list<ChildSalesOutputDto>
     */
    private function children(SalesReportAreaOutputDto $scope, array $current, bool $transmitted): array
    {
        $sales = [];
        $result = $this->sales->aggregate($scope->area, SalesBreakdownEnum::CHILD);
        while (false !== ($row = $result->fetch())) {
            $sales[(int)$row['ROW_ID']] = $row;
        }
        $children = [];
        foreach ($current as $child) {
            $children[] = $this->mapper->child($child->id, $child->code, $sales[$child->nativeId] ?? null, $transmitted ? $child->photoId : null);
            unset($sales[$child->nativeId]);
        }
        foreach ($sales as $row) {
            $children[] = $this->mapper->child((string)$row['CHILD_PUBLIC_ID'], (string)$row['NAME'], $row, null);
        }
        usort($children, static fn(ChildSalesOutputDto $left, ChildSalesOutputDto $right): int => [strlen($left->code), $left->code] <=> [strlen($right->code), $right->code]);

        return $children;
    }

    /**
     * Строки уровня из справочника групп (в том числе без продаж) и агрегат заказов по ним.
     *
     * @param array<int, int> $counts дети по native ID группы
     *
     * @return list<SalesReportRowOutputDto>
     */
    private function rows(SalesReportAreaOutputDto $scope, array $counts): array
    {
        $breakdown = match ($scope->level) {
            SalesReportLevelEnum::ALL => SalesBreakdownEnum::INSTITUTION,
            SalesReportLevelEnum::INSTITUTION => SalesBreakdownEnum::SHOOT,
            default => SalesBreakdownEnum::GROUP,
        };
        /** @var array<string, array{name: string, children: int}> $nodes */
        $nodes = [];
        foreach ($scope->groups as $group) {
            [$id, $name] = match ($breakdown) {
                SalesBreakdownEnum::INSTITUTION => [$group->institutionId, $group->institutionName],
                SalesBreakdownEnum::SHOOT => [$group->shootId, $group->shootName],
                default => [$group->id, $group->name],
            };
            $nodes[$id] ??= ['name' => $name, 'children' => 0];
            $nodes[$id]['children'] += $counts[$group->nativeId] ?? 0;
        }
        $sales = [];
        $result = $this->sales->aggregate($scope->area, $breakdown);
        while (false !== ($row = $result->fetch())) {
            $id = (string)$row['ROW_ID'];
            $sales[$id] = $row;
            $nodes[$id] ??= ['name' => (string)$row['NAME'], 'children' => 0];
        }
        if (SalesBreakdownEnum::INSTITUTION === $breakdown) {
            $collator = new \Collator('ru_RU');
            uasort($nodes, static fn(array $left, array $right): int => (int)$collator->compare($left['name'], $right['name']));
        }
        $rows = [];
        foreach ($nodes as $id => $node) {
            $rows[] = new SalesReportRowOutputDto((string)$id, $node['name'], $this->mapper->totals($sales[$id] ?? null, $node['children']));
        }

        return $rows;
    }
}
