<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\UseCase;

use Morefoto\Commerce\Application\Order\Contract\OrderStaffAccessInterface;
use Morefoto\Commerce\Application\Order\Dto\StaffOrderDetailOutputDto;
use Morefoto\Commerce\Application\Order\Mapper\OrderRowMapper;
use Morefoto\Commerce\Application\Order\Service\OrderPeriods;
use Morefoto\Commerce\Domain\Order\Repository\OrderRepository;
use Rebit\Share\Contracts\Media\ChildPhotosInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Открывает служебную карточку заказа организатору или куратору его учреждения без ключей покупателя.
 * Добавляет сроки группы и готовые кадры тех же детей для будущей формы исправления; чужой заказ неотличим от отсутствующего.
 */
final readonly class GetStaffOrderUseCase
{
    public function __construct(
        private OrderStaffAccessInterface $access,
        private OrderRepository $orders,
        private OrderRowMapper $mapper,
        private OrderPeriods $periods,
        private ChildPhotosInterface $photos,
    ) {}

    public function execute(int $actorId, string $orderId): StaffOrderDetailOutputDto
    {
        $scope = $this->access->scope($actorId);
        /** @var array<string, mixed>|false $row */
        $row = $this->orders->findVisible($orderId, $scope->institutionIds)->fetch();
        if (false === $row) {
            throw new HttpException('ORDER_NOT_FOUND', 404);
        }
        $lines = [];
        $children = [];
        $result = $this->orders->lines([(int)$row['ID']]);
        while (false !== ($line = $result->fetch())) {
            $lines[] = $line;
            $children[(int)$line['CHILD_ID']] = true;
        }
        $order = $this->mapper->order($row, $lines);
        $photos = $this->photos->ready((int)$row['GROUP_ID'], (int)$row['SHOOT_ID'], array_keys($children));
        $period = $this->periods->period($order->groupId);
        $this->access->assertUnchanged($actorId, $scope);

        return new StaffOrderDetailOutputDto($order, $period, $photos);
    }
}
