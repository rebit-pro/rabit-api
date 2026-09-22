<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Order\Service;

use Morefoto\Commerce\Application\Order\Dto\OrderOutputDto;
use Morefoto\Commerce\Application\Order\Mapper\OrderRowMapper;
use Morefoto\Commerce\Domain\Order\Repository\OrderRepository;
use Rebit\Share\Shared\Exception\HttpException;

/** Читает заказ со строками единым способом для ответа оформления, его повтора и просмотра по личному ключу. */
final readonly class OrderReader
{
    public function __construct(private OrderRepository $orders, private OrderRowMapper $mapper) {}

    public function read(int $orderId): OrderOutputDto
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->orders->find($orderId)->fetch();
        if (false === $row) {
            throw new HttpException('ORDER_NOT_FOUND', 404);
        }
        $lines = [];
        $result = $this->orders->lines([$orderId]);
        while (false !== ($line = $result->fetch())) {
            $lines[] = $line;
        }

        return $this->mapper->order($row, $lines);
    }
}
