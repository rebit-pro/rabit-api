<?php

declare(strict_types=1);

namespace Morefoto\Payment\Presentation\Receipt;

use Morefoto\Payment\Application\Receipt\Dto\HandOverPrintInputDto;
use Morefoto\Payment\Presentation\Receipt\Request\Dto\HandOverPrintRequestDto;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class ReceiptInputMapper
{
    public const string UUID_PATTERN = '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D';
    private const int MAX_ORDERS = 100;

    /** Список заказов: от 1 до 100 разных UUID, иначе 422 INVALID_ORDERS. */
    public function handOver(HandOverPrintRequestDto $request): HandOverPrintInputDto
    {
        $orderIds = [];
        foreach ($request->orderIds as $orderId) {
            if (1 !== preg_match(self::UUID_PATTERN, $orderId) || isset($orderIds[$orderId])) {
                throw new HttpException('INVALID_ORDERS', 422);
            }
            $orderIds[$orderId] = true;
        }
        if ([] === $orderIds || self::MAX_ORDERS < count($orderIds)) {
            throw new HttpException('INVALID_ORDERS', 422);
        }

        return new HandOverPrintInputDto($request->groupId, array_keys($orderIds), $request->confirmed);
    }
}
