<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\UseCase;

use Morefoto\Commerce\Application\Order\Dto\BuyerOrderOutputDto;
use Morefoto\Commerce\Application\Order\Service\OrderPeriods;
use Morefoto\Commerce\Application\Order\Service\OrderReader;
use Morefoto\Commerce\Domain\Order\Repository\OrderAccessKeyRepository;
use Morefoto\Commerce\Domain\Order\Service\OrderCalendarPolicy;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Открывает покупателю один заказ по действующему личному ключу без служебной учётной записи.
 * Неверный, отозванный и истёкший ключ неотличимы (404); номер MF и ссылка галереи доступа не дают.
 */
final readonly class GetBuyerOrderUseCase
{
    public function __construct(
        private OrderAccessKeyRepository $keys,
        private OrderReader $reader,
        private OrderPeriods $periods,
        private OrderCalendarPolicy $calendar,
        private ClockInterface $clock,
    ) {}

    public function execute(?string $orderKey): BuyerOrderOutputDto
    {
        if (null === $orderKey || 1 !== preg_match('/^[a-f0-9]{64}$/D', $orderKey)) {
            throw new HttpException('ORDER_NOT_FOUND', 404);
        }
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $key = $this->keys->findActive(hash('sha256', $orderKey), $now->format('Y-m-d H:i:s'));
        if (false === $key) {
            throw new HttpException('ORDER_NOT_FOUND', 404);
        }
        $order = $this->reader->read((int)$key['ORDER_ID']);

        return new BuyerOrderOutputDto(
            $order,
            $this->periods->period($order->groupId),
            $this->calendar->display(new \DateTimeImmutable($key['EXPIRES_AT'], new \DateTimeZone('UTC'))),
        );
    }
}
