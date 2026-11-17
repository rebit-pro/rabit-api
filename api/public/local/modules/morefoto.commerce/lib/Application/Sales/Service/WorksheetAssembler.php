<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Sales\Service;

use Morefoto\Commerce\Application\Sales\Dto\WorksheetColumnOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetFileOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetGroupOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetLineOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetOrderOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetOutputDto;
use Morefoto\Commerce\Application\Sales\Dto\WorksheetPurchaseOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;
use Rebit\Share\Contracts\Production\Dto\PrintCompositionOutputDto;
use Rebit\Share\Contracts\Production\Dto\PrintLineOutputDto;
use Rebit\Share\Contracts\Production\Dto\PrintVersionsOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Строит ведомость из снимков покупок, сохраняя ФИО и полную сумму на уровне одного заказа.
 * Для финального вида учитывает только исходные строки выбранного печатного задания, а текущие покупки и оплаты
 * отделяет; построчные скидки уже включены в сохранённый TOTAL и не распределяются заново.
 */
final readonly class WorksheetAssembler
{
    /**
     * @param list<WorksheetPurchaseOutputDto>            $purchases
     * @param array<string, list<WorksheetFileOutputDto>> $files
     * @param list<GroupDirectoryItemOutputDto>           $groups
     */
    public function assemble(array $purchases, array $files, array $groups, ?PrintCompositionOutputDto $composition, PrintVersionsOutputDto $versions, string $generatedAt): WorksheetOutputDto
    {
        $source = [];
        $names = [];
        foreach (null === $composition ? [] : $composition->lines as $line) {
            $source[$line->lineId] = $line;
            $names[$line->groupId] = $line->groupName;
        }
        /** @var array<string, list<WorksheetPurchaseOutputDto>> $orders */
        $orders = [];
        $seen = [];
        foreach ($purchases as $purchase) {
            if (isset($seen[$purchase->lineId])) {
                throw new HttpException('WORKSHEET_SNAPSHOT_MISMATCH', 409);
            }
            $seen[$purchase->lineId] = true;
            $orders[$purchase->orderId][] = $purchase;
        }
        $paidGroups = [];
        $deferredGroups = [];
        $outsideGroups = [];
        $totals = [];
        $printTotals = [];
        $columns = [];
        $electronicTotal = 0;
        $headers = [];
        foreach ($orders as $rows) {
            $first = $rows[0];
            $headers[$first->groupId] ??= $first;
            $children = [];
            $items = [];
            $orderSum = 0;
            $included = 0;
            $discount = 0;
            $electronic = 0;
            $prints = 0;
            $hasSelected = false;
            foreach ($rows as $purchase) {
                if ($purchase->groupId !== $first->groupId || $purchase->orderTotal !== $first->orderTotal) {
                    throw new HttpException('WORKSHEET_SNAPSHOT_MISMATCH', 409);
                }
                $children[$purchase->childPublicId] = $purchase->childCode;
                $orderSum += $purchase->total;
                $saved = $source[$purchase->lineId] ?? null;
                if (null !== $saved) {
                    $this->same($purchase, $saved);
                    unset($source[$purchase->lineId]);
                }
                $selected = null === $composition
                    ? $purchase->paid && !$purchase->coveredByGift
                    : null !== $saved;
                $key = hash('sha256', $purchase->productId . "\0" . $purchase->productName . "\0" . $purchase->format);
                if ('physical' === $purchase->kind) {
                    $columns[$key] ??= new WorksheetColumnOutputDto($key, $purchase->productName, $purchase->format);
                }
                if ($selected) {
                    $hasSelected = true;
                    $included += $purchase->total;
                    $discount += $purchase->discount;
                    if ('physical' === $purchase->kind) {
                        $prints += $purchase->quantity * $purchase->printCount;
                    }
                }
                if ($purchase->paid && !$purchase->coveredByGift && in_array($purchase->kind, ['digital', 'bundle'], true)) {
                    $electronic += $purchase->total;
                }
                $items[] = new WorksheetLineOutputDto(
                    id: $purchase->lineId,
                    kind: $purchase->kind,
                    columnKey: $key,
                    productName: $purchase->productName,
                    format: $purchase->format,
                    quantity: $purchase->quantity,
                    printCount: $purchase->printCount,
                    prints: 'physical' === $purchase->kind ? $purchase->quantity * $purchase->printCount : 0,
                    unitPrice: $purchase->unitPrice,
                    total: $purchase->total,
                    discount: $purchase->discount,
                    coveredByGift: $purchase->coveredByGift,
                    selected: $selected,
                    files: $files[$purchase->lineId] ?? [],
                );
            }
            if ($orderSum !== $first->orderTotal) {
                throw new HttpException('WORKSHEET_SNAPSHOT_MISMATCH', 409);
            }
            $gifts = json_decode($first->gifts, true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($gifts)) {
                throw new HttpException('WORKSHEET_SNAPSHOT_MISMATCH', 409);
            }
            $giftCodes = [];
            foreach ($gifts as $code) {
                if (!is_string($code)) {
                    throw new HttpException('WORKSHEET_SNAPSHOT_MISMATCH', 409);
                }
                $giftCodes[] = $code;
            }
            $order = new WorksheetOrderOutputDto(
                id: $first->orderId,
                number: $first->number,
                firstName: $first->firstName,
                lastName: $first->lastName,
                childCodes: array_values($children),
                ambiguous: 1 < count($children),
                paymentStatus: $first->paymentStatus,
                latePayment: $first->latePayment,
                total: $first->orderTotal,
                includedTotal: $included,
                discount: $discount,
                electronicTotal: $electronic,
                giftCodes: $giftCodes,
                lines: $items,
            );
            if (null !== $composition && !$hasSelected && $first->paid) {
                $outsideGroups[$first->groupId][] = $order;
            } elseif ($hasSelected || $first->paid) {
                $paidGroups[$first->groupId][] = $order;
            } else {
                $deferredGroups[$first->groupId][] = $order;
            }
            $totals[$first->groupId] = ($totals[$first->groupId] ?? 0) + $included;
            $printTotals[$first->groupId] = ($printTotals[$first->groupId] ?? 0) + $prints;
            $electronicTotal += $electronic;
        }
        if ([] !== $source) {
            throw new HttpException('WORKSHEET_SOURCE_MISSING', 409);
        }
        $result = [];
        foreach ($groups as $group) {
            $header = $headers[$group->id] ?? null;
            $result[] = new WorksheetGroupOutputDto(
                id: $group->id,
                name: $names[$group->id] ?? (null === $header ? $group->name : $header->groupName),
                institutionName: null === $header ? $group->institutionName : $header->institutionName,
                shootName: null === $header ? $group->shootName : $header->shootName,
                orders: $paidGroups[$group->id] ?? [],
                deferredOrders: $deferredGroups[$group->id] ?? [],
                outsideOrders: $outsideGroups[$group->id] ?? [],
                total: $totals[$group->id] ?? 0,
                prints: $printTotals[$group->id] ?? 0,
            );
        }
        $orderedColumns = array_values($columns);
        usort($orderedColumns, static fn(WorksheetColumnOutputDto $a, WorksheetColumnOutputDto $b): int => [$a->name, $a->format, $a->key] <=> [$b->name, $b->format, $b->key]);

        return new WorksheetOutputDto(
            mode: null === $composition ? 'preview' : 'final',
            archive: $composition?->version,
            finalAllowed: $versions->allowed,
            versions: $versions->versions,
            groups: $result,
            columns: $orderedColumns,
            total: array_sum($totals),
            electronicTotal: $electronicTotal,
            prints: array_sum($printTotals),
            generatedAt: $generatedAt,
        );
    }

    private function same(WorksheetPurchaseOutputDto $purchase, PrintLineOutputDto $saved): void
    {
        if ('physical' !== $purchase->kind || $purchase->orderId !== $saved->orderId || $purchase->groupId !== $saved->groupId
            || $purchase->photoId !== $saved->photoId || $purchase->productId !== $saved->productId
            || $purchase->productName !== $saved->productName || $purchase->format !== $saved->productFormat
            || $purchase->quantity !== $saved->quantity || $purchase->printCount !== $saved->printCount
            || $purchase->quantity * $purchase->printCount !== $saved->prints) {
            throw new HttpException('WORKSHEET_SNAPSHOT_MISMATCH', 409);
        }
    }
}
