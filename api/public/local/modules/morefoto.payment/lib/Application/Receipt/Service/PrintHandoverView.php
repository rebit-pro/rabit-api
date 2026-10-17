<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Receipt\Service;

use Morefoto\Payment\Application\Payment\Dto\PaymentStaffScopeOutputDto;
use Morefoto\Payment\Application\Payment\Mapper\PaymentOutputMapper;
use Morefoto\Payment\Application\Receipt\Dto\PrintHandoverOrderOutputDto;
use Morefoto\Payment\Application\Receipt\Dto\PrintHandoversOutputDto;
use Morefoto\Payment\Domain\Receipt\Enum\ReceiptKindEnum;
use Morefoto\Payment\Domain\Receipt\Enum\ReceiptStatusEnum;
use Morefoto\Payment\Domain\Receipt\Repository\PaymentReceiptRepositoryInterface;
use Rebit\Share\Contracts\Commerce\GroupPrintOrdersInterface;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Собирает выдачу оплаченной печати группы (G2-DEC-05): заказы с печатью, оплаченные до срока печати без опоздания
 * (тот же состав, что у архива печати L1), и для каждого — ждёт ли он выдачи, выдан ли (с состоянием закрывающего
 * чека) или закрывающий чек невозможен, потому что чек оплаты не пробивался или не зарегистрирован.
 *
 * @phpstan-import-type ReceiptRecord from PaymentReceiptRepositoryInterface
 */
final readonly class PrintHandoverView
{
    public const string WAITING = 'waiting';
    public const string HANDED = 'handed';
    public const string BLOCKED = 'blocked';

    public function __construct(
        private GroupDirectoryInterface $groups,
        private GroupPrintOrdersInterface $printOrders,
        private PaymentReceiptRepositoryInterface $receipts,
        private ReceiptSettings $settings,
        private PaymentOutputMapper $time,
    ) {}

    /** Группа в области сотрудника; чужая и отсутствующая неотличимы. */
    public function group(PaymentStaffScopeOutputDto $scope, string $groupId): GroupDirectoryItemOutputDto
    {
        $group = $this->groups->find($groupId);
        if (null === $group || (null !== $scope->institutionIds && !in_array($group->institutionNativeId, $scope->institutionIds, true))) {
            throw new HttpException('GROUP_NOT_FOUND', 404);
        }

        return $group;
    }

    /**
     * @return array{view: PrintHandoversOutputDto, payments: array<string, ReceiptRecord>} чеки оплаты заказов, ждущих выдачи
     */
    public function build(GroupDirectoryItemOutputDto $group): array
    {
        /** @var array<string, array{number: string, paidAt: string, children: array<string, true>, prints: int}> $orders */
        $orders = [];
        if (null !== $group->calendar->sentAt && null !== $group->calendar->closesAt) {
            foreach ($this->printOrders->paidPrintLines($group->id, new \DateTimeImmutable($group->calendar->closesAt)) as $line) {
                $orders[$line->orderId] ??= ['number' => $line->orderNumber, 'paidAt' => $line->paidAt, 'children' => [], 'prints' => 0];
                $orders[$line->orderId]['children'][$line->childCode] = true;
                $orders[$line->orderId]['prints'] += $line->quantity * $line->printCount;
            }
        }
        /** @var array<string, array<string, ReceiptRecord>> $receipts */
        $receipts = [];
        foreach ($this->receipts->forOrderPublicIds(array_keys($orders)) as $receipt) {
            $receipts[$receipt['ORDER_PUBLIC_ID']][$receipt['KIND']] = $receipt;
        }
        $items = [];
        $payments = [];
        $counts = [self::WAITING => 0, self::HANDED => 0, self::BLOCKED => 0];
        foreach ($orders as $orderId => $order) {
            $orderId = (string)$orderId;
            $payment = $receipts[$orderId][ReceiptKindEnum::PAYMENT->value] ?? null;
            $closing = $receipts[$orderId][ReceiptKindEnum::CLOSING->value] ?? null;
            $reason = match (true) {
                null !== $closing => null,
                null === $payment => 'no_payment_receipt',
                in_array(ReceiptStatusEnum::from($payment['STATUS']), [ReceiptStatusEnum::CANCELED, ReceiptStatusEnum::FAILED], true) => 'payment_receipt_failed',
                default => null,
            };
            $state = null !== $closing ? self::HANDED : (null === $reason ? self::WAITING : self::BLOCKED);
            if (self::WAITING === $state && null !== $payment) {
                $payments[$orderId] = $payment;
            }
            ++$counts[$state];
            $children = array_keys($order['children']);
            sort($children);
            $items[] = new PrintHandoverOrderOutputDto(
                orderId: $orderId,
                orderNumber: $order['number'],
                childCodes: array_map('strval', $children),
                prints: $order['prints'],
                paidAt: $this->time->display($order['paidAt']),
                state: $state,
                handedAt: null === $closing || null === $closing['HANDED_AT'] ? null : $this->time->display($closing['HANDED_AT']),
                receiptStatus: null === $closing ? null : ReceiptStatusEnum::from($closing['STATUS'])->visible(),
                reason: $reason,
            );
        }

        return [
            'view' => new PrintHandoversOutputDto($group->id, $group->name, $this->settings->enabled(), $items, $counts[self::WAITING], $counts[self::HANDED], $counts[self::BLOCKED]),
            'payments' => $payments,
        ];
    }
}
