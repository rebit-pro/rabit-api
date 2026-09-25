<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Infrastructure\Payment;

use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Domain\Order\Enum\PaymentStatusEnum;
use Morefoto\Commerce\Domain\Order\Repository\OrderAccessKeyRepository;
use Morefoto\Commerce\Domain\Order\Repository\OrderRepository;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Commerce\Dto\OrderPaymentInputDto;
use Rebit\Share\Contracts\Commerce\Dto\PayableOrderOutputDto;
use Rebit\Share\Contracts\Commerce\OrderPaymentInterface;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class OrderPayments implements OrderPaymentInterface
{
    public function __construct(
        private OrderAccessKeyRepository $keys,
        private OrderRepository $orders,
        private GroupCalendarInterface $calendars,
        private ClockInterface $clock,
    ) {}

    public function byKey(?string $orderKey): PayableOrderOutputDto
    {
        if (null === $orderKey || 1 !== preg_match('/^[a-f0-9]{64}$/D', $orderKey)) {
            throw new HttpException('ORDER_NOT_FOUND', 404);
        }
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $key = $this->keys->findActive(hash('sha256', $orderKey), $now);
        if (false === $key) {
            throw new HttpException('ORDER_NOT_FOUND', 404);
        }

        return $this->order($this->orders->find((int)$key['ORDER_ID']));
    }

    public function lock(int $orderId): PayableOrderOutputDto
    {
        return $this->order($this->orders->lock($orderId));
    }

    public function applyPayment(OrderPaymentInputDto $input): void
    {
        $status = PaymentStatusEnum::from($input->status);
        if (PaymentStatusEnum::UNPAID === $status || (PaymentStatusEnum::PAID === $status) !== (null !== $input->paidAt)) {
            throw new \InvalidArgumentException('A payment status change needs pending/declined, or paid with its moment.');
        }
        /** @var array{PAYMENT_STATUS: string, PAID_AT: null|string}|false $current */
        $current = $this->orders->find($input->orderId)->fetch();
        if (false === $current) {
            throw new HttpException('ORDER_NOT_FOUND', 404);
        }
        // A confirmed payment is final for the order; a repeated status keeps the version.
        if (PaymentStatusEnum::PAID->value === $current['PAYMENT_STATUS'] || ($status->value === $current['PAYMENT_STATUS'] && PaymentStatusEnum::PAID !== $status)) {
            return;
        }
        $this->orders->applyPayment($input->orderId, $status->value, $input->paidAt, $input->latePayment);
    }

    private function order(Result $result): PayableOrderOutputDto
    {
        /** @var array<string, mixed>|false $row */
        $row = $result->fetch();
        if (false === $row) {
            throw new HttpException('ORDER_NOT_FOUND', 404);
        }
        $closesAt = $this->calendars->get((string)$row['GROUP_PUBLIC_ID'])->calendar->closesAt;

        return new PayableOrderOutputDto(
            id: (int)$row['ID'],
            publicId: (string)$row['PUBLIC_ID'],
            number: (string)$row['NUMBER'],
            institutionId: (int)$row['INSTITUTION_ID'],
            institutionName: (string)$row['INSTITUTION_NAME'],
            groupName: (string)$row['GROUP_NAME'],
            subtotal: (int)$row['SUBTOTAL'],
            discount: (int)$row['DISCOUNT'],
            giftSaving: (int)$row['GIFT_SAVING'],
            total: (int)$row['TOTAL'],
            paymentStatus: (string)$row['PAYMENT_STATUS'],
            version: (string)$row['VERSION'],
            paidAt: null === $row['PAID_AT'] ? null : (string)$row['PAID_AT'],
            latePayment: 1 === (int)$row['LATE_PAYMENT'],
            closesAt: null === $closesAt ? null : new \DateTimeImmutable($closesAt)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        );
    }
}
