<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Sales;

use Morefoto\Commerce\Application\Sales\Dto\ChildSalesOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\OrderNameSalesOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\ProductSalesOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportInputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportNodeOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportRowOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\SalesTotalsOutputDto;
use Morefoto\Commerce\Presentation\Sales\Dto\ChildSalesResultDto;
use Morefoto\Commerce\Presentation\Sales\Dto\OrderNameSalesResultDto;
use Morefoto\Commerce\Presentation\Sales\Dto\ProductSalesResultDto;
use Morefoto\Commerce\Presentation\Sales\Dto\SalesReportNodeResultDto;
use Morefoto\Commerce\Presentation\Sales\Dto\SalesReportRequestDto;
use Morefoto\Commerce\Presentation\Sales\Dto\SalesReportResultDto;
use Morefoto\Commerce\Presentation\Sales\Dto\SalesReportRowResultDto;
use Morefoto\Commerce\Presentation\Sales\Dto\SalesTotalsResultDto;

/** Ответ COM-15 без данных покупателей: числа уровня, коды детей и признак миниатюры вместо ID кадра. */
final readonly class SalesReportResultMapper
{
    public function input(SalesReportRequestDto $request): SalesReportInputDto
    {
        return new SalesReportInputDto($request->institutionId, $request->shootId, $request->groupId);
    }

    public function report(SalesReportOutputDto $report): SalesReportResultDto
    {
        return new SalesReportResultDto(
            level: $report->level->value,
            institution: $this->node($report->institution),
            shoot: $this->node($report->shoot),
            group: $this->node($report->group),
            totals: $this->totals($report->totals),
            products: array_map(
                static fn(ProductSalesOutputDto $product): ProductSalesResultDto => new ProductSalesResultDto($product->id, $product->name, $product->prints),
                $report->products,
            ),
            rows: array_map(
                fn(SalesReportRowOutputDto $row): SalesReportRowResultDto => new SalesReportRowResultDto($row->id, $row->name, $this->totals($row->totals)),
                $report->rows,
            ),
            children: array_map(
                static fn(ChildSalesOutputDto $child): ChildSalesResultDto => new ChildSalesResultDto(
                    id: $child->id,
                    code: $child->code,
                    status: $child->status->value,
                    orders: $child->orders,
                    paidOrders: $child->paidOrders,
                    prints: $child->prints,
                    digitalFiles: $child->digitalFiles,
                    bundles: $child->bundles,
                    paidTotal: $child->paidTotal,
                    hasPreview: null !== $child->photoId,
                ),
                $report->children,
            ),
            generatedAt: $report->generatedAt->format(DATE_ATOM),
            orders: array_map(
                static fn(OrderNameSalesOutputDto $order): OrderNameSalesResultDto => new OrderNameSalesResultDto(
                    $order->id,
                    $order->number,
                    $order->firstName,
                    $order->lastName,
                    $order->paymentStatus,
                    $order->latePayment,
                    $order->total,
                ),
                $report->orders,
            ),
        );
    }

    private function node(?SalesReportNodeOutputDto $node): ?SalesReportNodeResultDto
    {
        return null === $node ? null : new SalesReportNodeResultDto($node->id, $node->name);
    }

    private function totals(SalesTotalsOutputDto $totals): SalesTotalsResultDto
    {
        return new SalesTotalsResultDto(
            children: $totals->children,
            orderedFamilies: $totals->orderedFamilies,
            paidFamilies: $totals->paidFamilies,
            paidOrders: $totals->paidOrders,
            prints: $totals->prints,
            digitalFiles: $totals->digitalFiles,
            bundles: $totals->bundles,
            paidTotal: $totals->paidTotal,
        );
    }
}
