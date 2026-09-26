<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Infrastructure\Files;

use Morefoto\Commerce\Domain\Catalog\Enum\ProductKind;
use Morefoto\Commerce\Domain\Order\Repository\OrderAccessKeyRepository;
use Morefoto\Commerce\Domain\Order\Repository\OrderRepository;
use Morefoto\Commerce\Domain\Order\Service\OrderCalendarPolicy;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Commerce\Dto\OrderEntitledPhotoOutputDto;
use Rebit\Share\Contracts\Commerce\Dto\OrderEntitlementOutputDto;
use Rebit\Share\Contracts\Commerce\OrderEntitlementInterface;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class OrderEntitlements implements OrderEntitlementInterface
{
    public function __construct(
        private OrderAccessKeyRepository $keys,
        private OrderRepository $orders,
        private OrderCalendarPolicy $calendar,
        private ClockInterface $clock,
    ) {}

    public function byKey(?string $orderKey): OrderEntitlementOutputDto
    {
        if (null === $orderKey || 1 !== preg_match('/^[a-f0-9]{64}$/D', $orderKey)) {
            throw new HttpException('ORDER_NOT_FOUND', 404);
        }
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $key = $this->keys->findActive(hash('sha256', $orderKey), $now);
        if (false === $key) {
            throw new HttpException('ORDER_NOT_FOUND', 404);
        }

        return $this->byId((int)$key['ORDER_ID']);
    }

    public function byId(int $orderId): OrderEntitlementOutputDto
    {
        /** @var array{ID: int|string, PUBLIC_ID: string, NUMBER: null|string, SHOOT_ID: int|string, PAYMENT_STATUS: string, PAID_AT: null|string, LATE_PAYMENT: int|string, GIFTS: string}|false $order */
        $order = $this->orders->find($orderId)->fetch();
        if (false === $order) {
            throw new HttpException('ORDER_NOT_FOUND', 404);
        }
        $gifts = json_decode($order['GIFTS'], true, 4, JSON_THROW_ON_ERROR);
        $giftCodes = array_fill_keys(array_map('strval', is_array($gifts) ? $gifts : []), true);
        $photos = [];
        $children = [];
        $result = $this->orders->lines([(int)$order['ID']]);
        while (false !== ($line = $result->fetch())) {
            /** @var array{CHILD_ID: int|string, CHILD_CODE: string, PHOTO_PUBLIC_ID: null|string, PHOTO_CODE: null|string, PRODUCT_KIND: string} $line */
            $kind = ProductKind::from($line['PRODUCT_KIND']);
            if (ProductKind::DIGITAL === $kind && null !== $line['PHOTO_PUBLIC_ID']) {
                $photos[] = new OrderEntitledPhotoOutputDto($line['PHOTO_PUBLIC_ID'], $line['CHILD_CODE'], (string)$line['PHOTO_CODE']);
            }
            // A gift is stored by child code and grants the whole set of that child, the same as a bought bundle line.
            if (ProductKind::BUNDLE === $kind || isset($giftCodes[$line['CHILD_CODE']])) {
                $children[(int)$line['CHILD_ID']] = true;
            }
        }
        $paidAt = $order['PAID_AT'];

        return new OrderEntitlementOutputDto(
            id: (int)$order['ID'],
            publicId: $order['PUBLIC_ID'],
            number: (string)$order['NUMBER'],
            shootId: (int)$order['SHOOT_ID'],
            paymentStatus: $order['PAYMENT_STATUS'],
            paidAt: $paidAt,
            latePayment: 1 === (int)$order['LATE_PAYMENT'],
            filesAvailableUntil: null === $paidAt ? null
                : $this->calendar->filesAvailableUntil(new \DateTimeImmutable($paidAt, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            photos: $photos,
            childIds: array_keys($children),
        );
    }
}
