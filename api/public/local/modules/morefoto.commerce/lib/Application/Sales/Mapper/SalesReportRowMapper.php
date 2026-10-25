<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Mapper;

use Morefoto\Commerce\Application\Sales\Dto\ChildSalesOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesTotalsOutputDto;
use Morefoto\Commerce\Domain\Sales\Service\ChildSalesStatusPolicy;

/** Строки агрегатов отчёта в числа DTO; отсутствующая строка означает нули. */
final readonly class SalesReportRowMapper
{
    public function __construct(private ChildSalesStatusPolicy $status) {}

    /** @param null|array<string, mixed> $row */
    public function totals(?array $row, int $children): SalesTotalsOutputDto
    {
        return new SalesTotalsOutputDto(
            children: $children,
            orderedFamilies: (int)($row['ORDERED_FAMILIES'] ?? 0),
            paidFamilies: (int)($row['PAID_FAMILIES'] ?? 0),
            paidOrders: (int)($row['PAID_ORDERS'] ?? 0),
            prints: (int)($row['PRINTS'] ?? 0),
            digitalFiles: (int)($row['DIGITAL'] ?? 0),
            bundles: (int)($row['BUNDLES'] ?? 0),
            paidTotal: (int)($row['PAID_TOTAL'] ?? 0),
        );
    }

    /** @param null|array<string, mixed> $row */
    public function child(string $id, string $code, ?array $row, ?string $photoId): ChildSalesOutputDto
    {
        $orders = (int)($row['ORDERS'] ?? 0);
        $paidOrders = (int)($row['PAID_ORDERS'] ?? 0);

        return new ChildSalesOutputDto(
            id: $id,
            code: $code,
            status: $this->status->status($orders, $paidOrders, (int)($row['LATE_ORDERS'] ?? 0)),
            orders: $orders,
            paidOrders: $paidOrders,
            prints: (int)($row['PRINTS'] ?? 0),
            digitalFiles: (int)($row['DIGITAL'] ?? 0),
            bundles: (int)($row['BUNDLES'] ?? 0),
            paidTotal: (int)($row['PAID_TOTAL'] ?? 0),
            photoId: $photoId,
        );
    }
}
